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
        // Validar el término de búsqueda
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $query = str_replace(' ', '+', $request->input('query'));

        try {
            // Crear cliente Guzzle con User-Agent para evitar bloqueos
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

            // Hacer solicitud a Google
            $response = $client->get("https://www.google.com/search?q={$query}");

            // Obtener el HTML de la respuesta
            $html = $response->getBody()->getContents();

            // Crear instancia de Crawler
            $crawler = new Crawler($html);

            $results = [];

            // Seleccionar los bloques de resultados de búsqueda (usar div.g como selector más genérico)
            $crawler->filter('div.g')->each(function (Crawler $node) use (&$results) {
                try {
                    // Extraer título (h3 dentro de div.g)
                    $title = $node->filter('h3')->count() ? $node->filter('h3')->text() : 'Sin título';

                    // Extraer URL (href del enlace principal)
                    $url = $node->filter('a')->count() ? $node->filter('a')->attr('href') : '';

                    // Limpiar URL si comienza con "/url?q="
                    if (strpos($url, '/url?q=') === 0) {
                        $url = parse_url(urldecode(substr($url, 7)), PHP_URL_QUERY);
                        parse_str($url, $params);
                        $url = $params['q'] ?? '';
                    }

                    // Extraer descripción (div dentro de div.g que contiene el snippet)
                    $description = $node->filter('div.VwiC3b, div.s3v9rd, span.aCOpRe')->count()
                        ? $node->filter('div.VwiC3b, div.s3v9rd, span.aCOpRe')->text()
                        : 'Sin descripción';

                    // Validar y agregar resultado
                    if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                        $results[] = [
                            'titulo' => $title,
                            'descripcion' => $description,
                            'url' => $url,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Error al procesar un nodo: ' . $e->getMessage());
                }
            });

            // Si es una solicitud API (detectar por Accept header o prefijo)
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json($results);
            }

            // Si es una solicitud web, renderizar la vista
            return view('search', ['results' => $results]);

        } catch (\Exception $e) {
            Log::error('Error en el web scraping de Google: ' . $e->getMessage());
            $error = ['error' => 'Error al realizar la búsqueda: ' . $e->getMessage()];

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json($error, 500);
            }
            return view('search', ['results' => $error]);
        }
    }
}