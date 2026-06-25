<?php

declare(strict_types=1);

namespace App\Services\Affiliate;

final class FanzaService extends AbstractAffiliateService
{
    public function name(): string { return 'fanza'; }
    public function search(string $keyword, array $credentials): array
    {
        if (empty($credentials['api_id']) || empty($credentials['affiliate_id'])) return $this->demo('fanza', $keyword);
        $query = http_build_query(['api_id'=>$credentials['api_id'],'affiliate_id'=>$credentials['affiliate_id'],'site'=>'FANZA','service'=>'digital','floor'=>'videoa','hits'=>20,'keyword'=>$keyword,'output'=>'json']);
        $data = $this->getJson('https://api.dmm.com/affiliate/v3/ItemList?' . $query);
        $items = $data['result']['items'] ?? [];
        return array_map(fn($i)=>['service'=>'fanza','external_id'=>(string)($i['content_id']??$i['product_id']??sha1(json_encode($i))),'title'=>(string)($i['title']??''),'actress'=>implode(',', array_column($i['iteminfo']['actress']??[], 'name')),'genre'=>implode(',', array_column($i['iteminfo']['genre']??[], 'name')),'article_url'=>$i['URL']??null,'affiliate_url'=>$i['affiliateURL']??null,'image_url'=>$i['imageURL']['large']??$i['imageURL']['small']??null,'sample_movie_url'=>$i['sampleMovieURL']['size_720_480']??$i['sampleMovieURL']['size_476_306']??null,'raw'=>$i], $items);
    }
}
