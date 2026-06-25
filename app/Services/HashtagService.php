<?php

declare(strict_types=1);

namespace App\Services;

final class HashtagService
{
    public function generate(array $product, array $ngWords): string
    {
        $source = implode(' ', [$product['title']??'', $product['genre']??'', $product['actress']??'', $product['service']??'']);
        $tokens = preg_split('/[\s,、。・\/\\|\[\]（）()「」【】:：]+/u', $source) ?: [];
        $tags=[];
        foreach ($tokens as $token) {
            $token = preg_replace('/[^\p{L}\p{N}_一-龠ぁ-んァ-ヶー]/u', '', $token) ?? '';
            if (mb_strlen($token) < 2 || mb_strlen($token) > 24) continue;
            foreach ($ngWords as $ng) if ($ng !== '' && mb_stripos($token, $ng) !== false) continue 2;
            $tags['#'.$token] = true;
            if (count($tags) >= 8) break;
        }
        return implode(' ', array_keys($tags));
    }
}
