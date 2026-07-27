<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Database, View};
use App\Models\Product;
use App\Services\SettingsService;
use App\Services\Affiliate\{DugaService, FanzaService, SokmilService};
use function App\Core\{flash, redirect, verify_csrf};

final class ProductController extends Controller
{
    private const SERVICES = ['fanza', 'duga', 'sokmil'];
    private function service(string $name): object { return match ($name) { 'duga'=>new DugaService(), 'sokmil'=>new SokmilService(), default=>new FanzaService() }; }

    public function index(): string
    {
        $this->requireAuth(); $settings = new SettingsService();
        return View::render('products/index', [
            'products'=>Product::all(), 'results'=>$_SESSION['search_results']??[],
            'apiSettings'=>['fanza'=>$settings->apiCredentials('fanza'),'duga'=>$settings->apiCredentials('duga'),'sokmil'=>$settings->apiCredentials('sokmil')],
        ]);
    }

    public function saveApi(): string
    {
        $this->requireAuth(); verify_csrf();
        $service=(string)($_POST['service']??'');
        if(!in_array($service,self::SERVICES,true)){ flash('error','対象サービスが正しくありません。'); redirect('/products'); }
        $credentials=is_array($_POST['credentials']??null)?$_POST['credentials']:[];
        (new SettingsService())->saveApi($service,array_map(static fn($v)=>trim((string)$v),$credentials));
        flash('success',strtoupper($service).'のAPI設定を保存しました。'); redirect('/products');
    }

    public function search(): string
    {
        $this->requireAuth(); verify_csrf();
        $services=array_values(array_intersect(self::SERVICES,array_map('strval',(array)($_POST['services']??[]))));
        if(!$services) $services=self::SERVICES;
        $keyword=trim((string)($_POST['keyword']??'')); $settings=new SettingsService(); $all=[]; $failed=[];
        foreach($services as $service){
            try{
                foreach($this->service($service)->search($keyword,$settings->apiCredentials($service)) as $row){ if(is_array($row)) $all[]=$row; }
            }catch(\Throwable){ $failed[] = strtoupper($service); }
        }
        $_SESSION['search_results']=$all;
        if($all) flash('success',count($all).'件の商品を一括取得しました。');
        else flash('error','商品を取得できませんでした。API設定を確認してください。');
        if($failed) flash('error',implode('・',$failed).'の取得に失敗しました。');
        redirect('/products');
    }

    public function save(): string
    {
        $this->requireAuth(); verify_csrf();
        $indexes=array_map('intval',(array)($_POST['indexes']??[]));
        if(isset($_POST['index'])) $indexes[]=(int)$_POST['index'];
        $rows=$_SESSION['search_results']??[]; $count=0;
        foreach(array_unique($indexes) as $index){ if(isset($rows[$index])&&is_array($rows[$index])){ Product::upsert($rows[$index]); $count++; } }
        flash($count?'success':'error',$count?$count.'件を投稿候補へ保存しました。':'保存する商品を選択してください。'); redirect('/products');
    }

    public function delete(): string
    {
        $this->requireAuth(); verify_csrf();
        $ids=array_map('intval',(array)($_POST['ids']??[])); if(isset($_POST['id']))$ids[]=(int)$_POST['id'];
        $ids=array_values(array_filter(array_unique($ids)));
        if(!$ids){flash('error','削除する商品を選択してください。');redirect('/products');}
        $marks=implode(',',array_fill(0,count($ids),'?'));
        Database::pdo()->prepare("DELETE FROM products WHERE id IN ($marks)")->execute($ids);
        flash('success',count($ids).'件の商品を削除しました。'); redirect('/products');
    }
}