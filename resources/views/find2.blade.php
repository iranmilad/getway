

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Vue</title>
    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <div id="app">
        <main class="py-3">
            <h3>Laravel Vue</h3>

            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header"><i class="cil-graph mr-1"></i>Signals Table Detect</div>
                    <div class="card-body">
                        <signal-table-vue data-Url="{{ route('conf',2) }}"></signal-table-vue>
                    </div>
                </div>
            </div>
            <!-- /.col-->
        </main>
    </div>
    <script src="{{ mix('/js/app.js') }}"></script>


</body>
</html>





