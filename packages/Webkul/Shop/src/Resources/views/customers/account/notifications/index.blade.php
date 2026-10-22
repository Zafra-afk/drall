<x-shop::layouts.account>
    <x-slot:title>
        @lang('shop::app.customers.account.notifications.title')
    </x-slot>

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <div class="mx-4 flex-auto max-md:mx-6 max-sm:mx-4">
        <div class="mb-8 flex items-center max-sm:mb-5">
            <a class="grid md:hidden" href="{{ route('shop.customers.account.index') }}" aria-label="@lang('shop::app.customers.account.home')">
                <span class="icon-arrow-left rtl:icon-arrow-right text-2xl"></span>
            </a>

            <h2 class="text-2xl font-medium ltr:ml-2.5 rtl:mr-2.5 max-sm:text-base md:ltr:ml-0 md:rtl:mr-0">
                @lang('shop::app.customers.account.notifications.title')
            </h2>
        </div>

        @forelse ($notifications as $notification)
            <article class="mb-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-2 flex items-center justify-between gap-4">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100">
                        @lang('shop::app.customers.account.notifications.from-admin')
                    </h3>

                    <time class="shrink-0 text-sm text-gray-500 dark:text-gray-400" datetime="{{ $notification->created_at->toIso8601String() }}">
                        {{ $notification->created_at->format('d/m/Y H:i') }}
                    </time>
                </div>

                <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $notification->note }}</p>
            </article>
        @empty
            <p class="rounded-lg border border-gray-200 p-5 text-gray-600 dark:border-gray-700 dark:text-gray-300">
                @lang('shop::app.customers.account.notifications.empty')
            </p>
        @endforelse
    </div>
</x-shop::layouts.account>
