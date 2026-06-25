<?php

declare(strict_types=1);

namespace App\Services;

final class PostTemplateService
{
    public function render(string $body, array $product, string $hashtags): string
    {
        $map = ['{title}'=>$product['title']??'', '{article_url}'=>$product['article_url']??'', '{affiliate_url}'=>$product['affiliate_url']??'', '{sample_movie_url}'=>$product['sample_movie_url']??'', '{image_url}'=>$product['image_url']??'', '{hashtags}'=>$hashtags, '{service}'=>$product['service']??'', '{actress}'=>$product['actress']??'', '{genre}'=>$product['genre']??''];
        return trim(strtr($body, $map));
    }
}
