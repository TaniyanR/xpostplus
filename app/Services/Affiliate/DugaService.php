<?php

declare(strict_types=1);

namespace App\Services\Affiliate;

final class DugaService extends AbstractAffiliateService
{
    public function name(): string
    {
        return 'duga';
    }

    public function search(string $keyword, array $credentials): array
    {
        if (empty($credentials['appid']) || empty($credentials['agentid'])) {
            return $this->demo('duga', $keyword);
        }

        $query = http_build_query([
            'version' => '1.2',
            'appid' => $credentials['appid'],
            'agentid' => $credentials['agentid'],
            'bannerid' => $credentials['bannerid'] ?? '01',
            'keyword' => $keyword,
            'format' => 'json',
            'adult' => '1',
            'hits' => 20,
        ]);

        $data = $this->getJson('https://affapi.duga.jp/search?' . $query);
        $items = $data['items'] ?? $data['result']['items'] ?? [];

        return array_map(
            static function (array $row): array {
                $item = $row['item'] ?? $row;
                $jacket = $item['jacketimage'] ?? null;
                $sample = $item['samplemovie'] ?? null;

                if (is_array($jacket)) {
                    $imageUrl = $jacket[2]['large'] ?? $jacket[1]['medium'] ?? $jacket[0]['small'] ?? null;
                } else {
                    $imageUrl = $jacket ?: ($item['image'] ?? null);
                }

                if (is_array($sample)) {
                    $sampleMovieUrl = $sample[0]['large']['movie']
                        ?? $sample[0]['medium']['movie']
                        ?? $sample[0]['midium']['movie']
                        ?? null;
                } else {
                    $sampleMovieUrl = $sample;
                }

                return [
                    'service' => 'duga',
                    'external_id' => (string)($item['productid'] ?? $item['id'] ?? sha1(json_encode($item))),
                    'title' => (string)($item['title'] ?? ''),
                    'actress' => is_array($item['performer'] ?? null)
                        ? implode(',', $item['performer'])
                        : (string)($item['performer'] ?? ''),
                    'genre' => is_array($item['category'] ?? null)
                        ? implode(',', $item['category'])
                        : (string)($item['category'] ?? ''),
                    'article_url' => $item['url'] ?? null,
                    'affiliate_url' => $item['affiliateurl'] ?? $item['affiliate_url'] ?? null,
                    'image_url' => $imageUrl,
                    'sample_movie_url' => $sampleMovieUrl,
                    'raw' => $item,
                ];
            },
            $items
        );
    }
}
