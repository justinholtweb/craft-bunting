{{-- Bunting harness: each flag through @feature and $bunting->active() --}}
@foreach (['bunting-nav', 'bunting-off', 'bunting-on', 'bunting-pct', 'bunting-public', 'bunting-nope'] as $h)
<p data-flag="{{ $h }}">@feature($h) on @else off @endfeature/{{ $bunting->active($h) ? 'on' : 'off' }}</p>
@endforeach
<p id="any">@featureany(['bunting-off', 'bunting-on']) on @else off @endfeatureany/@featureany(['bunting-off', 'bunting-nope']) on @else off @endfeatureany</p>
<p id="site">{{ $currentSite->handle }}</p>
