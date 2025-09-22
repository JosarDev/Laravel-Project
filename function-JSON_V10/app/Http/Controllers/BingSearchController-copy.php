<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
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
            // Crear cliente HttpBrowser con configuración HTTP
            $client = new HttpBrowser(HttpClient::create([
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                ],
                'timeout' => 15,
                'verify_peer' => false, // Desactiva verificación SSL para pruebas (usar solo en desarrollo)
            ]));

            // Depuración: Verifica la solicitud
            \Log::info("Solicitando: https://www.bing.com/search?q={$query}");
            $crawler = $client->request('GET', "https://www.bing.com/search?q={$query}");

            // Depuración: Muestra el HTML recibido
            \Log::info("HTML recibido: " . $crawler->html());

            // Extraer resultados
            $results = [];
            $crawler->filter('li.b_algo')->each(function (Crawler $node) use (&$results) {
                $titleNode = $node->filter('h2 a');
                $title = $titleNode->count() ? trim($titleNode->text()) : null;

                $href = $titleNode->count() ? $titleNode->attr('href') : null;
                $url = null;
                if ($href) {
                    if (preg_match('/u=([^&]+)/', $href, $matches)) {
                        $url = urldecode($matches[1]);
                    } elseif (strpos($href, 'http') === 0) {
                        $url = $href;
                    }
                }

                $descNode = $node->filter('div.b_caption p');
                $description = $descNode->count() ? trim($descNode->text()) : 'Sin descripción';

                if ($title && $url) {
                    $results[] = [
                        'titulo' => $title,
                        'descripcion' => $description,
                        'url' => $url,
                    ];
                }
            });

            // Limita a 10 resultados
            $results = array_slice($results, 0, 10);

            return response()->json($results);
        } catch (\Exception $e) {
            // Depuración: Registra el error completo
            \Log::error("Error en búsqueda: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'error' => 'Error al procesar la búsqueda',
                'detalle' => $e->getMessage(),
                'query' => $query
            ], 500);
        }
    }
}
