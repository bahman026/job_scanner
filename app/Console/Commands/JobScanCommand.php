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
        $keywords = [];
        for ($i = 1; $i <= 4; $i++) {
            if ($this->argument('keyword' . $i) == null) {
                continue;
            }
            $keywords[] = $this->argument('keyword' . $i);
        }
        echo "start" . PHP_EOL;
        echo "Receiving information" . PHP_EOL;
        $resultFile = fopen("result.json", "w") or die("Unable to open file!");
        $companyLinks = JobScanner::getJobs($keywords);
        fwrite($resultFile, json_encode($companyLinks, JSON_UNESCAPED_UNICODE));
        fclose($resultFile);
        echo "finish";
    }
}
