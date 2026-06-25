<?php

declare(strict_types=1);

namespace App\Services\Affiliate;

final class DugaService extends AbstractAffiliateService
{
    public function name(): string { return 'duga'; }
    public function search(string $keyword, array $credentials): array
    {
        if (empty($credentials['appid'])) return $this->demo('duga', $keyword);
        $query=http_build_query(['version'=>'1.2','appid'=>$credentials['appid'],'keyword'=>$keyword,'format'=>'json','hits'=>20]);
        $data=$this->getJson('https://affapi.duga.jp/search?' . $query);
        $items=$data['items'] ?? $data['result']['items'] ?? [];
        return array_map(fn($row)=>($i=$row['item']??$row) ? ['service'=>'duga','external_id'=>(string)($i['productid']??$i['id']??sha1(json_encode($i))),'title'=>(string)($i['title']??''),'actress'=>is_array($i['performer']??null)?implode(',', $i['performer']):($i['performer']??''),'genre'=>is_array($i['category']??null)?implode(',', $i['category']):($i['category']??''),'article_url'=>$i['url']??null,'affiliate_url'=>$i['affiliateurl']??$i['affiliate_url']??null,'image_url'=>$i['jacketimage']??$i['image']??null,'sample_movie_url'=>$i['samplemovie']??null,'raw'=>$i] : [], $items);
    }
}
