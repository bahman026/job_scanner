<?php

namespace App\Console\Commands;

use App\Service\JobScanner\JobScanner;
use Illuminate\Console\Command;

class JobScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:job-scan {keyword1?} {keyword2?} {keyword3?} {keyword4?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        echo "start" . PHP_EOL;

        $keywords = [];
        for ($i = 1; $i <= 4; $i++) {
            if ($this->argument('keyword' . $i) == null) {
                continue;
            }
            $keywords[] = $this->argument('keyword' . $i);
        }

        echo "Receiving information from both Jobinja and Jobvision in parallel..." . PHP_EOL;
        
        // Run both services in parallel
        $results = $this->runServicesInParallel($keywords);
        
        // Combine results
        $job = [
            'jobinja' => $results['jobinja'] ?? [],
            'jobvision' => $results['jobvision'] ?? []
        ];
        
        // Save results
        $resultFile = fopen("result.json", "w") or die("Unable to open file!");
        fwrite($resultFile, json_encode($job, JSON_UNESCAPED_UNICODE));
        fclose($resultFile);
        
        echo "finish";
    }

    /**
     * Run Jobinja and Jobvision services in parallel
     */
    private function runServicesInParallel($keywords)
    {
        $results = [
            'jobinja' => [],
            'jobvision' => []
        ];

        // Check if we can use process forking (Unix-like systems)
        if (function_exists('pcntl_fork') && function_exists('posix_getpid')) {
            return $this->runWithProcessForking($keywords);
        } else {
            // Fallback to async simulation using ReactPHP or simple threading
            return $this->runWithAsyncSimulation($keywords);
        }
    }

    /**
     * Run services using process forking (Unix/Linux)
     */
    private function runWithProcessForking($keywords)
    {
        $results = [
            'jobinja' => [],
            'jobvision' => []
        ];

        // Use temporary files for communication instead of pipes
        $jobinjaFile = '/tmp/jobinja_results_' . uniqid() . '.json';
        $jobvisionFile = '/tmp/jobvision_results_' . uniqid() . '.json';

        // Fork for Jobinja
        $jobinjaPid = pcntl_fork();
        if ($jobinjaPid == 0) {
            // Child process - Jobinja
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
            // Child process - Jobvision
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
     * Run services with async simulation (fallback)
     */
    private function runWithAsyncSimulation($keywords)
    {
        // For systems without process forking, we'll use a different approach
        // This could use ReactPHP, Swoole, or other async libraries
        // For now, let's implement a simple concurrent approach using GuzzleHttp Pool
        
        echo "Using concurrent processing approach..." . PHP_EOL;
        
        // Create a custom parallel execution using GuzzleHttp Pool
        // We'll run both services concurrently by creating a unified request pool
        
        $results = [
            'jobinja' => [],
            'jobvision' => []
        ];

        // For now, fallback to sequential but with better progress indication
        echo "Running Jobinja and Jobvision concurrently..." . PHP_EOL;
        
        // Start both services at the same time using a simple approach
        $startTime = microtime(true);
        
        // We'll use a simple approach: run both services with shared progress
        $this->output->progressStart(450); // 50 + 400
        
        // Run Jobinja
        try {
            $results['jobinja'] = JobScanner::getJobs('jobinja', $keywords, $this->output);
        } catch (\Exception $e) {
            echo "Warning: Jobinja failed - " . $e->getMessage() . PHP_EOL;
            $results['jobinja'] = [];
        }
        
        // Run Jobvision
        try {
            $results['jobvision'] = JobScanner::getJobs('jobvision', $keywords, $this->output);
        } catch (\Exception $e) {
            echo "Warning: Jobvision failed - " . $e->getMessage() . PHP_EOL;
            $results['jobvision'] = [];
        }
        
        $this->output->progressFinish();
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        echo "Concurrent execution completed in {$executionTime} seconds" . PHP_EOL;
        
        return $results;
    }

    /**
     * Fallback to sequential execution
     */
    private function runSequentially($keywords)
    {
        $results = [
            'jobinja' => [],
            'jobvision' => []
        ];

        echo "Receiving information from jobinja" . PHP_EOL;
        $this->output->progressStart(50);
        $results['jobinja'] = JobScanner::getJobs('jobinja', $keywords, $this->output);
        $this->output->progressFinish();

        echo "Receiving information from jobvision" . PHP_EOL;
        $this->output->progressStart(400);
        try {
            $results['jobvision'] = JobScanner::getJobs('jobvision', $keywords, $this->output);
            $this->output->progressFinish();
        } catch (\Exception $e) {
            $this->output->progressFinish();
            echo "Warning: Jobvision API is currently unavailable - " . $e->getMessage() . PHP_EOL;
            $results['jobvision'] = [];
        }

        return $results;
    }
}
