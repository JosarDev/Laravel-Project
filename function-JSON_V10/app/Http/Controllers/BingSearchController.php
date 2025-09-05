<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class BingSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $query = str_replace(' ', '+', $request->input('query'));

        try {
            $client = new Client([
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Connection' => 'keep-alive',
                ],
                'timeout' => 10.0,
                'http_errors' => false,
            ]);

            $response = $client->get("https://www.bing.com/search?q={$query}");
            $html = $response->getBody()->getContents();
            Log::info($html);

            $crawler = new Crawler($html);
            $results = [];

            $crawler->filter('li.b_algo')->each(function (Crawler $node) use (&$results) {
                try {
                    $title = $node->filter('h2 a')->count() ? $node->filter('h2 a')->text() : 'Sin título';
                    $url = $node->filter('h2 a')->count() ? $node->filter('h2 a')->attr('href') : '';
                    $description = $node->filter('div.b_caption p')->count() ? $node->filter('div.b_caption p')->text() : 'Sin descripción';

                    if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                        $results[] = [
                            'titulo' => $title,
                            'descripcion' => $description,
                            'url' => $url,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Error procesando nodo Bing: ' . $e->getMessage());
                }
            });

            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Error en el web scraping de Bing: ' . $e->getMessage());
            return response()->json(['error' => 'Error al realizar la búsqueda: ' . $e->getMessage()], 500);
        }
    }
}
