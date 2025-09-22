<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class GoogleSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $query = str_replace(' ', '+', $request->input('query'));
        $responseData = null;

        try {
            $client = new Client([
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 11.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.6422.112 Safari/537.36',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Connection' => 'keep-alive',
                ],
                'timeout' => 10.0,
                'http_errors' => false,
            ]);

            $response = $client->get("https://www.google.com/search?q={$query}");
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);

            $results = $this->parseGoogleResults($crawler);

            if ($request->expectsJson() || $request->is('api/*')) {
                $responseData = response()->json($results);
            } else {
                $responseData = view('search', ['results' => $results]);
            }
        } catch (\Exception $e) {
            Log::error('Error en el web scraping de Google: ' . $e->getMessage());
            $error = ['error' => 'Error al realizar la búsqueda: ' . $e->getMessage()];

            if ($request->expectsJson() || $request->is('api/*')) {
                $responseData = response()->json($error, 500);
            } else {
                $responseData = view('search', ['results' => $error]);
            }
        }

        return $responseData;
    }

    /**
     * Parse Google search results from the crawler.
     *
     * @param Crawler $crawler
     * @return array
     */
    private function parseGoogleResults(Crawler $crawler): array
    {
        $results = [];

        $crawler->filter('div.g')->each(function (Crawler $node) use (&$results) {
            $title = $this->extractTitle($node);
            $url = $this->extractUrl($node);
            $description = $this->extractDescription($node);

            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $results[] = [
                    'titulo' => $title,
                    'descripcion' => $description,
                    'url' => $url,
                ];
            }
        });

        return $results;
    }

    /**
     * Extract the title from a result node.
     */
    private function extractTitle(Crawler $node): string
    {
        try {
            return $node->filter('h3')->count() ? $node->filter('h3')->text() : 'Sin título';
        } catch (\Exception $e) {
            Log::warning('Error al extraer el título: ' . $e->getMessage());
            return 'Sin título';
        }
    }

    /**
     * Extract the URL from a result node.
     */
    private function extractUrl(Crawler $node): string
    {
        try {
            $url = $node->filter('a')->count() ? $node->filter('a')->attr('href') : '';
            if (strpos($url, '/url?q=') === 0) {
                $urlQuery = parse_url(urldecode(substr($url, 7)), PHP_URL_QUERY);
                parse_str($urlQuery, $params);
                $url = $params['q'] ?? '';
            }
            return $url;
        } catch (\Exception $e) {
            Log::warning('Error al extraer la URL: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract the description from a result node.
     */
    private function extractDescription(Crawler $node): string
    {
        try {
            return $node->filter('div.VwiC3b, div.s3v9rd, span.aCOpRe')->count()
                ? $node->filter('div.VwiC3b, div.s3v9rd, span.aCOpRe')->text()
                : 'Sin descripción';
        } catch (\Exception $e) {
            Log::warning('Error al extraer la descripción: ' . $e->getMessage());
            return 'Sin descripción';
        }
    }
}
