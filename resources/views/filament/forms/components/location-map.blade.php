@php
    $record = $getRecord();
    $lat = $record->last_lat;
    $lng = $record->last_lng;
@endphp

@if($lat && $lng)
<div class="w-full h-96 rounded-xl border border-gray-200 overflow-hidden shadow-sm" style="min-height: 400px;">
    <iframe 
        width="100%" 
        height="100%" 
        frameborder="0" 
        scrolling="no" 
        marginheight="0" 
        marginwidth="0" 
        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $lng - 0.01 }}%2C{{ $lat - 0.01 }}%2C{{ $lng + 0.01 }}%2C{{ $lat + 0.01 }}&amp;layer=mapnik&amp;marker={{ $lat }}%2C{{ $lng }}" 
        style="border: 1px solid black"
    ></iframe>
    <div class="p-2 text-xs text-gray-500 bg-gray-50 flex justify-between items-center">
        <span>📍 Coordinates: {{ $lat }}, {{ $lng }}</span>
        <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank" class="text-primary-600 font-bold hover:underline">
            View on Google Maps →
        </a>
    </div>
</div>
@else
<div class="p-4 bg-gray-50 rounded-lg text-center text-gray-500 italic">
    No location data available for this carrier yet.
</div>
@endif
