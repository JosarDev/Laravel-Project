<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador Google</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            max-width: 800px;
        }

        .search-form {
            margin-bottom: 20px;
        }

        .search-form input[type="text"] {
            padding: 8px;
            width: 300px;
        }

        .search-form button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }

        .result {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .result h3 {
            margin: 0 0 5px;
            font-size: 18px;
        }

        .result a {
            color: #1a0dab;
            text-decoration: none;
        }

        .result a:hover {
            text-decoration: underline;
        }

        .error {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>Buscador Google</h1>
    <form class="search-form" action="{{ route('search.web') }}" method="GET">
        <input type="text" name="query" placeholder="Ej: Josar Monterrosa" value="{{ old('query') }}" required>
        <button type="submit">Buscar</button>
    </form>

    @if (isset($results))
        @if (isset($results['error']))
            <p class="error">{{ $results['error'] }}</p>
        @else
            <h2>Resultados:</h2>
            @foreach ($results as $result)
                <div class="result">
                    <h3>{{ $result['titulo'] }}</h3>
                    <p>{{ $result['descripcion'] }}</p>
                    <a href="{{ $result['url'] }}" target="_blank">{{ $result['url'] }}</a>
                </div>
            @endforeach
        @endif
    @endif
</body>

</html>
