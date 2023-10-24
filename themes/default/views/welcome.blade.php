<x-app-layout title="home">
    <x-success class="mt-4" />

    @if (config('settings::home_page_text'))
        <div class="content ">
            <div class="content-box">
                <div class="prose dark:prose-invert min-w-full">
                    @markdownify(config('settings::home_page_text'))
                </div>
            </div>
        </div>
    @endif

    @if ($categories->count() > 0)
        <div class="content">
            <h2 class="font-semibold text-2xl mb-2 text-secondary-900">{{ __('Categories') }}</h2>
            <div class="grid grid-cols-12 gap-4">

                @foreach ($categories as $category)
                    @if ($category->products->count() > 0)
                        <div class="lg:col-span-3 md:col-span-6 col-span-12">
                            <div class="content-box h-full flex flex-col">
                                <h3 class="font-semibold text-lg">{{ $category->name }}</h3>
                                <p>{{ $category->description }}</p>
                                <div class="pt-3 mt-auto">
                                    <a href="{{ route('products', $category->slug) }}"
                                    class="button button-secondary w-full">{{ __('Browse Category') }}</a>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach

            </div>
        </div>
    @endif

    @if ($announcements->count() > 0)
        <div class="content">
            <h2 class="font-semibold text-2xl mb-2 text-secondary-900">{{ __('Announcements') }}</h2>
            <div class="grid grid-cols-12 gap-4">
                @foreach ($announcements->sortByDesc('created_at') as $announcement)
                    <div class="lg:col-span-4 md:col-span-6 col-span-12">
                        <div class="content-box">
                            <h3 class="font-semibold text-lg">{{ $announcement->title }}</h3>
                            <p>@markdownify(substr($announcement->announcement, 0, 100) . '...')</p>
                            <div class="flex justify-between items-center mt-3">
                                <span class="text-sm text-secondary-600">{{ __('Published') }}
                                    {{ $announcement->created_at->diffForHumans() }}</span>
                                <a href="{{ route('announcements.view', $announcement->id) }}"
                                    class="button button-secondary">{{ __('Read More') }}</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="content">
        <h2 class="font-semibold text-2xl mb-2 text-secondary-900">Locations</h2>
        <button class="button button-secondary" id="startPingTest" style="margin-bottom: 20px; margin-right: 10px;">Start Ping Test</button>
        <small>Note: Ping times are approximate and may not be 100% accurate.</small>
        <div id="locationsGrid" class="grid grid-cols-12 gap-4">
            <!-- Content boxes will be appended here -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/alfg/ping.js@0.2.2/dist/ping.min.js" type="text/javascript"></script>
    <script>
    // Define an array of node locations
    const nodes = [
        { name: 'Michigan, US', url: 'https://ping.node1.suhosting.net', id: 'node1', flag: '/img/US-Flag.webp' },
        { name: 'New Jersey, US', url: 'https://ping.node2.suhosting.net', id: 'node2', flag: '/img/US-Flag.webp' }
        // Add more nodes here
    ];

    // Loop through the array and append content boxes to the grid
    const grid = document.getElementById('locationsGrid');
    nodes.forEach(node => {
        const contentBox = document.createElement('div');
        contentBox.className = 'lg:col-span-3 md:col-span-6 col-span-12';
        contentBox.innerHTML = `
            <div class="content-box h-full flex flex-col">
                <div class="d-flex align-items-center" style="display: flex; align-items: center;">
                    <img src="${node.flag}" alt="Flag" width="40" height="30" style="margin-right: 10px;">
                    <h3 class="font-semibold text-lg">${node.name}</h3>
                </div>
                <span id="${node.id}" class="large-text">Ready for ping test.</span><br>
            </div>
        `;
        grid.appendChild(contentBox);
    });

        function performPing(node) {
            var latencyElement = document.getElementById(node.id);
            latencyElement.innerText = "Checking..."; 
            latencyElement.style.color = 'white';
            latencyElement.classList.add('large-text'); // Add the large-text class
            var pinger = new Ping();

            // First warm-up ping
            pinger.ping(node.url, function(err, data) {
                // Second ping after the first one completes
                pinger.ping(node.url, function(err, data) {
                    if (err) {
                        latencyElement.innerText = "Ping failed";
                        latencyElement.style.color = 'grey'; // Set color to grey if failed
                    } else {
                        latencyElement.innerText = "Ping time: " + data.toFixed(2) + " ms";

                        // Set color based on ping time
                        if (data <= 100) {
                            latencyElement.style.color = 'green';
                        } else if (data > 100 && data <= 150) {
                            latencyElement.style.color = 'yellow';
                        } else {
                            latencyElement.style.color = 'red';
                        }
                    }
                });
            });
        }

        document.getElementById('startPingTest').addEventListener('click', function() {
            // Loop through the array and perform pings
            nodes.forEach(performPing);
        });
    </script>

</x-app-layout>