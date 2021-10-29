<?php

namespace App\Console\Commands;

use App\Models\MynaviUrl;
use App\Models\Mynavijobs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScrapeMynavi extends Command
{
    const HOST = 'https://tenshoku.mynavi.jp';
    const FILE_PATH = 'app/mynavi_jobs.csv';
    const PAGE_NUM = 1;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:mynavi';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape Mynavi';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->truncateTables();
        $this->saveUrls();
        $this->saveJobs();
        $this->exportCsv();
    }

    // テーブルを空にする
    private function truncateTables()
    {
        DB::table('mynavi_urls')->truncate();
        DB::table('mynavi_jobs')->truncate();
    }

    // 多数の URL を取得しデータベースに保存
    private function saveUrls()
    {
        foreach (range(1, $this::PAGE_NUM) as $num) {
            $url = $this::HOST . '/list/pg' . $num . '/';
            $crawler = \Goutte::request('GET', $url);
            $urls = $crawler->filter('.cassetteRecruit__copy > a')->each(function($node) {
              $href = $node->attr('href');
              return [
                // 文字列を切り取る：2文字目の「/」足す1文字å
                'url' => substr($href, 0, strpos($href, '/', 1) + 1), 
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
              ];
            });
            DB::table('mynavi_urls')->insert($urls);
            sleep(30); // 30秒間隔を空ける
        }
    }

    // 業務の詳細情報を取得
    private function saveJobs()
    {
        foreach (MynaviUrl::all() as $index => $mynaviUrl) {
          $url = $this::HOST . $mynaviUrl->url;
          $crawler = \Goutte::request('GET', $url);
            $title = $crawler->filter('.occName')->text();

            // 注意点: セキュリティの問題で複数の値を書き込めない　（Mass assignment）
            MynaviJobs::create([
              'url' => $url,
              'title' => $this->getTitle($crawler),
              'company_name' => $this->getCompanyName($crawler),
              'features' => $this->getFeatures($crawler),
            ]);

            if ($index > 2) {
                break;
            }
            
            sleep(30);
        }
    }

    // タイトルを取得
    private function getTitle($crawler)
    {
        return $crawler->filter('.occName')->text();
    }

    // 会社名を取得
    private function getCompanyName($crawler)
    {
        return $crawler->filter('.companyName')->text();
    }

    // 特徴を取得
    private function getFeatures($crawler)
    {
        $features = $crawler->filter('.cassetteRecruit__attribute.cassetteRecruit__attribute-jobinfo .cassetteRecruit__attributeLabel span')
        ->each(function ($node) {
            return $node->text();
        });
        return implode(',', $features);
    }

    // 出力： .csv 
    private function exportCsv()
    {
        $file = fopen(storage_path($this::FILE_PATH), 'w');
        if (!$file) {
            throw new \Exception('ファイルの作成に失敗しました');
        }

        if (!fputcsv($file, ['id', 'url', 'title', 'company_name', 'features'])) {
            throw new \Exception('ヘッダの書き込みに失敗しました');
        };

        foreach (MynaviJobs::all() as $job) {
          if (!fputcsv($file, [$job->id, $job->url, $job->title, $job->company_name, $job->features])) {
              throw new \Exception('ボディの書き込みに失敗しました');
          }
        }
        
        fclose($file);
    }
}
