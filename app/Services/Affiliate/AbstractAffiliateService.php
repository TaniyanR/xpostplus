<?php

declare(strict_types=1);

namespace App\Services\Affiliate;

abstract class AbstractAffiliateService implements AffiliateServiceInterface
{
    protected function getJson(string $url): array
    {
        $context = stream_context_create(['http' => ['timeout' => 12, 'header' => "User-Agent: XPostPlus/1.0\r\n"]]);
        $json = @file_get_contents($url, false, $context);
        if ($json === false) return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
    protected function demo(string $service, string $keyword): array
    {
        return [[
            'service'=>$service,'external_id'=>'demo-'.sha1($service.$keyword),'title'=>$keyword ?: $service.' サンプル商品',
            'actress'=>'サンプル女優','genre'=>'サンプルジャンル','article_url'=>'https://example.com/article','affiliate_url'=>'https://example.com/affiliate',
            'image_url'=>'https://placehold.co/600x400?text='.rawurlencode($service),'sample_movie_url'=>'https://example.com/sample.mp4','raw'=>['demo'=>true]
        ]];
    }
}
