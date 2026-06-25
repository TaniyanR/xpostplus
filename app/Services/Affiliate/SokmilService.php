<?php

declare(strict_types=1);

namespace App\Services\Affiliate;

final class SokmilService extends AbstractAffiliateService
{
    public function name(): string { return 'sokmil'; }
    public function search(string $keyword, array $credentials): array
    {
        if (empty($credentials['affiliate_id'])) return $this->demo('sokmil', $keyword);
        // ソクミルは契約アカウントごとにAPI仕様が異なる場合があるため、endpointを管理画面から指定可能にします。
        $endpoint = rtrim((string)($credentials['endpoint'] ?? ''), '?');
        if ($endpoint === '') return $this->demo('sokmil', $keyword);
        $data = $this->getJson($endpoint . '?' . http_build_query(['keyword'=>$keyword,'affiliate_id'=>$credentials['affiliate_id']]));
        $items = $data['items'] ?? $data['result']['items'] ?? [];
        return array_map(fn($i)=>['service'=>'sokmil','external_id'=>(string)($i['id']??$i['product_id']??sha1(json_encode($i))),'title'=>(string)($i['title']??''),'actress'=>is_array($i['actress']??null)?implode(',', $i['actress']):($i['actress']??''),'genre'=>is_array($i['genre']??null)?implode(',', $i['genre']):($i['genre']??''),'article_url'=>$i['url']??null,'affiliate_url'=>$i['affiliate_url']??$i['affiliateURL']??null,'image_url'=>$i['image_url']??null,'sample_movie_url'=>$i['sample_movie_url']??null,'raw'=>$i], $items);
    }
}
