<?php

namespace App\Http\Controllers;

use App\Service\JobScanner\Exceptions\SourceUnavailableException;
use App\Service\JobScanner\JobScanner;
use App\Service\JobScanner\Support\SearchOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JobSearchController extends Controller
{
    public function index()
    {
        return view('job-search-clean');
    }

    public function search(Request $request)
    {
        $request->validate([
            'keywords' => 'required|string|max:255',
            'city' => 'nullable|string|max:100',
            'sort' => 'nullable|in:' . SearchOptions::SORT_RELEVANCE . ',' . SearchOptions::SORT_NEWEST,
        ]);

        $options = SearchOptions::fromArray($request->only(['city', 'sort']));

        $keywords = array_filter(explode(',', $request->keywords));
        $keywords = array_map('trim', $keywords);
        
        // Clean and validate keywords
        $keywords = array_filter($keywords, function($keyword) {
            return !empty($keyword) && strlen($keyword) >= 2;
        });
        
        if (empty($keywords)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter valid keywords (at least 2 characters each)'
            ], 400);
        }
        
        // Log the search attempt
        \Log::info('Job search started', [
            'keywords' => $keywords,
            'original_input' => $request->keywords,
            'city' => $options->city,
            'sort' => $options->sort,
        ]);

        try {
            // Run the job scanner
            $startTime = microtime(true);
            
            // Use sequential processing for web context (more reliable)
            [$results, $sources] = $this->runSequentially($keywords, $options);

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            // Log results for debugging
            \Log::info('Job search completed', [
                'keywords' => $keywords,
                'execution_time' => $executionTime,
                'jobinja_companies' => count($results['jobinja']),
                'jobvision_companies' => count($results['jobvision']),
                'total_jobs' => $this->countTotalJobs($results),
                'sources' => $sources,
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'sources' => $sources,
                'execution_time' => $executionTime,
                'total_companies' => count($results['jobinja']) + count($results['jobvision']),
                'total_jobs' => $this->countTotalJobs($results),
                'keywords_used' => $keywords,
                'filters' => ['city' => $options->city, 'sort' => $options->sort],
            ]);

        } catch (\Exception $e) {
            Log::error('Job search failed: ' . $e->getMessage(), [
                'keywords' => $keywords,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching for jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    private function runWithProcessForking($keywords)
    {
        $results = [
            'jobinja' => [],
            'jobvision' => []
        ];

        $jobinjaFile = '/tmp/jobinja_results_' . uniqid() . '.json';
        $jobvisionFile = '/tmp/jobvision_results_' . uniqid() . '.json';

        // Fork for Jobinja
        $jobinjaPid = pcntl_fork();
        if ($jobinjaPid == 0) {
            try {
                $jobinjaResults = JobScanner::getJobs('jobinja', $keywords, null);
                file_put_contents($jobinjaFile, json_encode($jobinjaResults));
            } catch (\Exception $e) {
                file_put_contents($jobinjaFile, json_encode([]));
            }
            exit(0);
        }

        // Fork for Jobvision
        $jobvisionPid = pcntl_fork();
        if ($jobvisionPid == 0) {
            try {
                $jobvisionResults = JobScanner::getJobs('jobvision', $keywords, null);
                file_put_contents($jobvisionFile, json_encode($jobvisionResults));
            } catch (\Exception $e) {
                file_put_contents($jobvisionFile, json_encode([]));
            }
            exit(0);
        }

        // Parent process - wait for children
        pcntl_waitpid($jobinjaPid, $status);
        pcntl_waitpid($jobvisionPid, $status);

        // Read results
        if (file_exists($jobinjaFile)) {
            $results['jobinja'] = json_decode(file_get_contents($jobinjaFile), true) ?? [];
            unlink($jobinjaFile);
        }

        if (file_exists($jobvisionFile)) {
            $results['jobvision'] = json_decode(file_get_contents($jobvisionFile), true) ?? [];
            unlink($jobvisionFile);
        }

        return $results;
    }

    /**
     * Scan each source in turn. A failing source yields no results plus an
     * entry in $sources saying why, so the UI can tell "blocked" from "no matches".
     *
     * @return array{0: array<string, array>, 1: array<string, array{ok: bool, error: ?string}>}
     */
    private function runSequentially($keywords, SearchOptions $options): array
    {
        $results = [];
        $sources = [];

        foreach (['jobinja' => 'Jobinja', 'jobvision' => 'Jobvision'] as $site => $label) {
            try {
                $results[$site] = JobScanner::getJobs($site, $keywords, null, $options);
                $sources[$site] = ['ok' => true, 'error' => null];
            } catch (SourceUnavailableException $e) {
                Log::warning("{$label} unavailable: " . $e->getMessage());
                $results[$site] = [];
                $sources[$site] = ['ok' => false, 'error' => $e->getMessage()];
            } catch (\Exception $e) {
                Log::warning("{$label} failed: " . $e->getMessage(), ['exception' => $e]);
                $results[$site] = [];
                // Don't leak internal error details to the browser.
                $sources[$site] = ['ok' => false, 'error' => "{$label} search failed unexpectedly."];
            }
        }

        return [$results, $sources];
    }

    private function countTotalJobs($results)
    {
        $total = 0;
        foreach ($results['jobinja'] as $company) {
            $total += count($company['jobs']);
        }
        foreach ($results['jobvision'] as $company) {
            $total += count($company['jobs']);
        }
        return $total;
    }
}
