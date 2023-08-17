<?php

namespace App\Service\JobScanner;

use GuzzleHttp\Exception\GuzzleException;
use Mockery\Exception;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\CurlException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;

class CompanyLocation
{
    /**
     * @param $link
     * @return mixed|null
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws CurlException
     * @throws NotLoadedException
     * @throws StrictException
     */
    public static function getLocation($link): mixed
    {
        try {
            $dom = new Dom;
            $dom->loadFromUrl($link);
            $links = $dom->find('.c-companyMap__desc');
            return ($links->text);
        } catch (Exception $exception) {
            return null;
        }
    }
}
