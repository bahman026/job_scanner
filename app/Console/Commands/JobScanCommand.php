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
        echo "Receiving information from jobinja" . PHP_EOL;
        $this->output->progressStart(50);
        $job['jobinja'] = JobScanner::getJobs('jobinja', $keywords, $this->output);
        $this->output->progressFinish();
        echo "Receiving information from jobvision" . PHP_EOL;
        $this->output->progressStart(400);
        $job['jobvision'] = JobScanner::getJobs('jobvision', $keywords, $this->output);
        $this->output->progressFinish();
        $resultFile = fopen("result.json", "w") or die("Unable to open file!");
        fwrite($resultFile, json_encode($job, JSON_UNESCAPED_UNICODE));
        fclose($resultFile);
        echo "finish";
    }
}
