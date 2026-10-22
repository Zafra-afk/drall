<!DOCTYPE html>

<html
    lang="{{ app()->getLocale() }}"
    dir="{{ core()->getCurrentLocale()->direction }}"
>

<head>
    <title>{{ ! empty($title) ? $title . ' - ' . config('app.name') : config('app.name') }}</title>

    <meta charset="UTF-8">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >
    <meta
        http-equiv="content-language"
        content="{{ app()->getLocale() }}"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="base-url"
        content="{{ url()->to('/') }}"
    >
    <meta 
        name="generator" 
        content="Drall"
    >

    @stack('meta')

    @bagistoVite(['src/Resources/assets/css/app.css', 'src/Resources/assets/js/app.js'])

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    />

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap"
        rel="stylesheet"
    />

    @if ($favicon = core()->getConfigData('general.design.admin_logo.favicon'))
        <link
            type="image/x-icon"
            href="{{ Storage::url($favicon) }}"
            rel="shortcut icon"
            sizes="16x16"
        />
    @else
        <link
            type="image/x-icon"
            href="{{ bagisto_asset('images/favicon.ico') }}"
            rel="shortcut icon"
            sizes="16x16"
        />
    @endif

    @stack('styles')

    <style>
        {!! core()->getConfigData('general.content.custom_scripts.custom_css') !!}
    </style>

    {!! view_render_event('bagisto.admin.layout.head') !!}
</head>

<body>
    {!! view_render_event('bagisto.admin.layout.body.before') !!}

    <!-- Built With Drall -->
    <div id="app">
        <label class="sr-only" for="admin-anonymous-locale-switcher">Langue / Fiteny</label>
        <select
            id="admin-anonymous-locale-switcher"
            class="fixed right-4 top-4 z-10 rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-700"
            onchange="const url = new URL(window.location.href); url.searchParams.set('admin_locale', this.value); window.location.assign(url.toString());"
        >
            @foreach (config('app.allowed_locales', ['fr']) as $locale)
                <option value="{{ $locale }}" @selected(app()->getLocale() === $locale)>
                    {{ $locale === 'fr' ? 'Français' : 'Malagasy' }}
                </option>
            @endforeach
        </select>

        <!-- Flash Message Blade Component -->
        <x-admin::flash-group />

        {!! view_render_event('bagisto.admin.layout.content.before') !!}

        <!-- Page Content Blade Component -->
        {{ $slot }}

        {!! view_render_event('bagisto.admin.layout.content.after') !!}
    </div>

    {!! view_render_event('bagisto.admin.layout.body.after') !!}

    @stack('scripts')

    {!! view_render_event('bagisto.admin.layout.vue-app-mount.before') !!}

    <script>
        /**
         * Load event, the purpose of using the event is to mount the application
         * after all of our `Vue` components which is present in blade file have
         * been registered in the app. No matter what `app.mount()` should be
         * called in the last.
         */
        window.addEventListener("load", function(event) {
            app.mount("#app");
        });
    </script>

    {!! view_render_event('bagisto.admin.layout.vue-app-mount.after') !!}

    <script type="text/javascript">
        {!! core()->getConfigData('general.content.custom_scripts.custom_javascript') !!}
    </script>
</body>

</html>
