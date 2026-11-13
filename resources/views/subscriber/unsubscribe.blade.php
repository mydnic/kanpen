<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Unsubscribe</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 40px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .card h1 { margin-top: 0; }
        .card p { color: #666; }
        .card button {
            font: inherit;
            cursor: pointer;
            border: 0;
            border-radius: 6px;
            padding: 12px 24px;
            background-color: #1f2937;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Unsubscribe</h1>
        <p>Please confirm that you no longer want to receive our emails.</p>
        <form method="POST" action="{{ route(config('kanpen.routes.unsubscribe_name', 'kanpen.unsubscribe').'.destroy', ['token' => $token]) }}">
            @csrf
            @method('DELETE')
            <button type="submit">Confirm unsubscribe</button>
        </form>
    </div>
</body>
</html>
