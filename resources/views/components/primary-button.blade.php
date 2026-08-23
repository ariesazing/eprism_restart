<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-5 py-2.5 bg-cherry-700 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-cherry-800 focus:bg-cherry-800 active:bg-cherry-900 focus:outline-none focus:ring-2 focus:ring-cherry-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
