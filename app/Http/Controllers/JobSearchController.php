<?php

namespace App\Http\Controllers;

use App\Service\JobScanner\JobScanner;
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
        ]);

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
            'original_input' => $request->keywords
        ]);

        try {
            // Run the job scanner
            $startTime = microtime(true);
            
            $results = [
                'jobinja' => [],
                'jobvision' => []
            ];

            // Use sequential processing for web context (more reliable)
            $results = $this->runSequentially($keywords);

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            // Log results for debugging
            \Log::info('Job search completed', [
                'keywords' => $keywords,
                'execution_time' => $executionTime,
                'jobinja_companies' => count($results['jobinja']),
                'jobvision_companies' => count($results['jobvision']),
                'total_jobs' => $this->countTotalJobs($results)
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'execution_time' => $executionTime,
                'total_companies' => count($results['jobinja']) + count($results['jobvision']),
                'total_jobs' => $this->countTotalJobs($results),
                'keywords_used' => $keywords
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

    private function runSequentially($keywords)
    {
        $results = [
            'jobinja' => [],
            'jobvision' => []
        ];

        try {
            $results['jobinja'] = JobScanner::getJobs('jobinja', $keywords, null);
        } catch (\Exception $e) {
            Log::warning('Jobinja failed: ' . $e->getMessage());
            $results['jobinja'] = [];
        }

        try {
            $results['jobvision'] = JobScanner::getJobs('jobvision', $keywords, null);
        } catch (\Exception $e) {
            Log::warning('Jobvision failed: ' . $e->getMessage());
            $results['jobvision'] = [];
        }

        return $results;
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
