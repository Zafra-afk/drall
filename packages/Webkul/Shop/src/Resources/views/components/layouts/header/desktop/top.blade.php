{!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.before') !!}

@php
    $allowedChannelLocales = core()->getCurrentChannel()
        ->locales()
        ->whereIn('code', config('app.allowed_locales', ['fr']))
        ->orderBy('name')
        ->get();
@endphp

<v-topbar>
    <!-- Shimmer Effect -->
    <div class="flex items-center justify-between border border-b border-l-0 border-r-0 border-t-0 px-16">
        <!-- Currencies -->
        <div class="flex w-20 items-center justify-between gap-2.5 py-3">
            <div
                class="shimmer h-6 w-12 rounded-sm"
                role="presentation"
            >
            </div>

            <div
                class="shimmer h-6 w-6 rounded-sm"
                role="presentation"
            >
            </div>
        </div>

        <!-- Offers -->
        <div
            class="shimmer h-6 w-72 rounded-sm py-3"
            role="presentation"
        >
        </div>

        <!-- Locales -->
        <div class="flex w-32 items-center justify-between gap-2.5 py-3">
            <div
                class="shimmer h-6 w-6"
                role="presentation"
            >
            </div>

            <div
                class="shimmer h-6 w-14 rounded-sm"
                role="presentation"
            >
            </div>

            <div
                class="shimmer h-6 w-6"
                role="presentation"
            >
            </div>
        </div>

        <div
            class="shimmer h-6 w-6 rounded-sm"
            role="presentation"
        >
        </div>
    </div>
</v-topbar>

{!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-topbar-template"
    >
        <div class="flex w-full items-center justify-between border border-b border-l-0 border-r-0 border-t-0 px-16">
            @if (core()->getCurrentChannel()->currencies()->count() > 1)
                {!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.currency_switcher.before') !!}

            <!-- Currency Switcher -->
            <x-shop::dropdown position="bottom-{{ core()->getCurrentLocale()->direction === 'ltr' ? 'left' : 'right' }}">
                <!-- Dropdown Toggler -->
                <x-slot:toggle>
                    <button
                        type="button"
                        class="flex cursor-pointer gap-2.5 py-3"
                        @click="currencyToggler = ! currencyToggler"
                    >
                        <span v-pre>
                            {{ core()->getCurrentCurrency()->symbol . ' ' . core()->getCurrentCurrencyCode() }}
                        </span>

                        <span
                            class="text-2xl"
                            :class="{'icon-arrow-up': currencyToggler, 'icon-arrow-down': ! currencyToggler}"
                            role="presentation"
                        >
                        </span>
                    </button>
                </x-slot>

                <!-- Dropdown Content -->
                <x-slot:content class="journal-scroll max-h-125 p-0!">
                    <v-currency-switcher></v-currency-switcher>
                </x-slot>
            </x-shop::dropdown>

                {!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.currency_switcher.after') !!}
            @endif

            <p
                class="py-3 text-xs font-medium"
                v-pre
            >
                {{ core()->getConfigData('general.content.header_offer.title') }}
                
                <a 
                    href="{{ core()->getConfigData('general.content.header_offer.redirection_link') ?: route('shop.home.index') }}" 
                    class="underline"
                >
                    {{ core()->getConfigData('general.content.header_offer.redirection_title') }}
                </a>
            </p>

            {!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.locale_switcher.before') !!}

            <!-- Locales Switcher -->
            @if ($allowedChannelLocales->count() > 1)
            <x-shop::dropdown position="bottom-{{ core()->getCurrentLocale()->direction === 'ltr' ? 'right' : 'left' }}">
                <x-slot:toggle>
                    <!-- Dropdown Toggler -->
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2.5 py-3"
                        @click="localeToggler = ! localeToggler"
                    >
                        <img
                            src="{{ ! empty(core()->getCurrentLocale()->logo_url)
                                    ? core()->getCurrentLocale()->logo_url
                                    : bagisto_asset('images/default-language.svg')
                                }}"
                            class="h-full"
                            alt="@lang('shop::app.components.layouts.header.desktop.top.default-locale')"
                            width="24"
                            height="16"
                        />
                        
                        <span v-pre>
                            {{ $allowedChannelLocales->firstWhere('code', app()->getLocale())?->name }}
                        </span>

                        <span
                            class="text-2xl"
                            :class="{'icon-arrow-up': localeToggler, 'icon-arrow-down': ! localeToggler}"
                            role="presentation"
                        ></span>
                    </button>
                </x-slot>
            
                <!-- Dropdown Content -->
                <x-slot:content class="journal-scroll max-h-125 p-0!">
                    <v-locale-switcher></v-locale-switcher>
                </x-slot>
            </x-shop::dropdown>
            @endif

            {!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.locale_switcher.after') !!}

        </div>
    </script>

    <script
        type="text/x-template"
        id="v-currency-switcher-template"
    >
        <div class="my-2.5 grid gap-1 overflow-auto max-md:my-0 sm:max-h-125">
            <button
                type="button"
                class="w-full cursor-pointer px-5 py-2 text-base hover:bg-gray-100 focus-visible:bg-gray-100 focus-visible:outline-hidden ltr:text-left rtl:text-right"
                v-for="currency in currencies"
                :class="{'bg-gray-100': currency.code == '{{ core()->getCurrentCurrencyCode() }}'}"
                @click="change(currency)"
            >
                @{{ currency.symbol + ' ' + currency.code }}
            </button>
        </div>
    </script>

    <script
        type="text/x-template"
        id="v-locale-switcher-template"
    >
        <div class="my-2.5 grid gap-1 overflow-auto max-md:my-0 sm:max-h-125">
            <button
                type="button"
                class="flex w-full cursor-pointer items-center gap-2.5 px-5 py-2 text-base hover:bg-gray-100 focus-visible:bg-gray-100 focus-visible:outline-hidden ltr:text-left rtl:text-right"
                :class="{'bg-gray-100': locale.code == '{{ app()->getLocale() }}'}"
                v-for="locale in locales"
                @click="change(locale)"                  
            >
                <img
                    :src="locale.logo_url || '{{ bagisto_asset('images/default-language.svg') }}'"
                    width="24"
                    height="16"
                />

                @{{ locale.name }}
            </button>
        </div>
    </script>

    <script type="module">
        app.component('v-topbar', {
            template: '#v-topbar-template',

            data() {
                return {
                    localeToggler: '',

                    currencyToggler: '',
                };
            },
        });

        app.component('v-currency-switcher', {
            template: '#v-currency-switcher-template',

            data() {
                return {
                    currencies: @json(core()->getCurrentChannel()->currencies),
                };
            },

            methods: {
                change(currency) {
                    let url = new URL(window.location.href);

                    url.searchParams.set('currency', currency.code);

                    window.location.href = url.href;
                }
            }
        });

        app.component('v-locale-switcher', {
            template: '#v-locale-switcher-template',

            data() {
                return {
                    locales: @json($allowedChannelLocales),
                };
            },

            methods: {
                change(locale) {
                    let url = new URL(window.location.href);

                    url.searchParams.set('locale', locale.code);

                    window.location.href = url.href;
                }
            }
        });
    </script>

    <script
        type="text/x-template"
        id="v-dark-top-template"
    >
        <div class="flex items-center">
            <button
                type="button"
                class="cursor-pointer rounded-md p-1.5 transition-all hover:bg-gray-100 dark:hover:bg-gray-800"
                @click="toggle"
                :title="isDarkMode ? 'Mode clair' : 'Mode sombre'"
                :aria-label="isDarkMode ? 'Mode clair' : 'Mode sombre'"
            >
                <!-- Sun icon when dark mode active -->
                <svg
                    v-if="isDarkMode"
                    class="h-5 w-5 text-yellow-500"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>

                <!-- Moon icon when light mode active -->
                <svg
                    v-else
                    class="h-5 w-5 text-gray-700 dark:text-gray-200"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
        </div>
    </script>

    <script type="module">
        app.component('v-dark-topbar', {
            template: '#v-dark-top-template',

            data() {
                return {
                    isDarkMode: this.getInitialDarkMode(),
                }
            },

            methods: {
                getDarkModeCookie() {
                    const cookies = document.cookie.split(';');

                    for (const cookie of cookies) {
                        const [name, value] = cookie.trim().split('=');

                        if (name === 'dark_mode') {
                            return value;
                        }
                    }

                    return null;
                },

                getInitialDarkMode() {
                    const cookie = this.getDarkModeCookie();

                    if (cookie === '1') {
                        return 1;
                    }

                    if (cookie === '0') {
                        return 0;
                    }

                    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 1 : 0;
                },

                toggle() {
                    this.isDarkMode = this.isDarkMode ? 0 : 1;

                    var expiryDate = new Date();

                    expiryDate.setMonth(expiryDate.getMonth() + 1);

                    document.cookie = 'dark_mode=' + this.isDarkMode + '; path=/; expires=' + expiryDate.toGMTString() + '; SameSite=Lax';

                    document.documentElement.classList.toggle('dark', this.isDarkMode === 1);

                    if (this.isDarkMode) {
                        this.$emitter.emit('change-theme', 'dark');
                    } else {
                        this.$emitter.emit('change-theme', 'light');
                    }
                },
            },
        });
    </script>
@endPushOnce