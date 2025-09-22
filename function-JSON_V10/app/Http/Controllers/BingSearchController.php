<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

class BingSearchController extends Controller
{
    // Constantes para configuración
    private const MAX_RESULTS = 10;
    private const BASE_URL = 'https://www.bing.com/search';
    private const DEFAULT_DESCRIPTION = 'Sin descripción';

    /**
     * Realiza una búsqueda en Bing y devuelve los resultados en formato JSON.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->validateRequest($request);

        $query = $this->sanitizeQuery($request->input('query'));

        try {
            $client = $this->createHttpClient();
            $crawler = $this->fetchSearchResults($client, $query);
            $results = $this->extractResults($crawler);

            return response()->json(array_slice($results, 0, self::MAX_RESULTS));
        } catch (\Exception $e) {
            return $this->handleException($e, $query);
        }
    }

    /**
     * Valida los datos de entrada de la solicitud.
     *
     * @param Request $request
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateRequest(Request $request): void
    {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);
    }

    /**
     * Sanitiza el query reemplazando espacios por '+'.
     *
     * @param string $query
     * @return string
     */
    private function sanitizeQuery(string $query): string
    {
        return str_replace(' ', '+', $query);
    }

    /**
     * Crea una instancia del cliente HTTP con configuración predeterminada.
     *
     * @return HttpBrowser
     */
    private function createHttpClient(): HttpBrowser
    {
        return new HttpBrowser(HttpClient::create([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            ],
            'timeout' => 15,
            'verify_peer' => false, // Solo para desarrollo; activar en producción
        ]));
    }

    /**
     * Realiza la solicitud a Bing y devuelve el Crawler.
     *
     * @param HttpBrowser $client
     * @param string $query
     * @return Crawler
     * @throws \Exception
     */
    private function fetchSearchResults(HttpBrowser $client, string $query): Crawler
    {
        Log::info("Solicitando: " . self::BASE_URL . "?q={$query}");
        return $client->request('GET', self::BASE_URL . "?q={$query}");
    }

    /**
     * Extrae los resultados de la búsqueda desde el Crawler.
     *
     * @param Crawler $crawler
     * @return array
     */
    private function extractResults(Crawler $crawler): array
    {
        $results = [];
        $crawler->filter('li.b_algo')->each(function (Crawler $node) use (&$results) {
            $title = $this->extractTitle($node);
            $url = $this->extractUrl($node);
            $description = $this->extractDescription($node);

            if ($title && $url) {
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
     * Extrae el título de un nodo de resultado.
     *
     * @param Crawler $node
     * @return string|null
     */
    private function extractTitle(Crawler $node): ?string
    {
        $titleNode = $node->filter('h2 a');
        return $titleNode->count() ? trim($titleNode->text()) : null;
    }

    /**
     * Extrae la URL de un nodo de resultado.
     *
     * @param Crawler $node
     * @return string|null
     */
    private function extractUrl(Crawler $node): ?string
    {
        $titleNode = $node->filter('h2 a');
        $href = $titleNode->count() ? $titleNode->attr('href') : null;

        if ($href) {
            if (preg_match('/u=([^&]+)/', $href, $matches)) {
                return urldecode($matches[1]);
            } elseif (strpos($href, 'http') === 0) {
                return $href;
            }
        }

        return null;
    }

    /**
     * Extrae la descripción de un nodo de resultado.
     *
     * @param Crawler $node
     * @return string
     */
    private function extractDescription(Crawler $node): string
    {
        $descNode = $node->filter('div.b_caption p');
        return $descNode->count() ? trim($descNode->text()) : self::DEFAULT_DESCRIPTION;
    }

    /**
     * Maneja las excepciones y devuelve una respuesta de error.
     *
     * @param \Exception $e
     * @param string $query
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleException(\Exception $e, string $query): \Illuminate\Http\JsonResponse
    {
        Log::error("Error en búsqueda: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'error' => 'Error al procesar la búsqueda',
            'detalle' => $e->getMessage(),
            'query' => $query
        ], 500);
    }
}
