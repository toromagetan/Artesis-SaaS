<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.fitting.title') }} — {{ $customer->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">
                {{ __('ui.fitting.advisory') }}
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="font-semibold text-gray-800">{{ __('ui.fitting.camera_title') }}</h2>
                    <video id="cam" autoplay playsinline muted class="mt-3 w-full rounded-lg bg-gray-900" style="transform: scaleX(-1);"></video>
                    <p id="camStatus" class="mt-2 text-sm text-gray-500">{{ __('ui.fitting.loading') }}</p>
                    <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-lg bg-lentera-50 px-2 py-3">
                            <p class="text-xs uppercase tracking-wide text-gray-500">PD</p>
                            <p class="text-xl font-semibold tnum" id="livePd">—</p>
                        </div>
                        <div class="rounded-lg bg-lentera-50 px-2 py-3">
                            <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('ui.fitting.face_width') }}</p>
                            <p class="text-xl font-semibold tnum" id="liveFw">—</p>
                        </div>
                        <div class="rounded-lg bg-lentera-50 px-2 py-3">
                            <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('ui.fitting.size') }}</p>
                            <p class="text-xl font-semibold" id="liveSize">—</p>
                        </div>
                    </div>
                    <button type="button" id="useReading" class="mt-4 w-full rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white hover:bg-lentera-800">{{ __('ui.fitting.use_reading') }}</button>
                    <p class="mt-2 text-xs text-gray-400">{{ __('ui.fitting.privacy') }}</p>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="font-semibold text-gray-800">{{ __('ui.fitting.form_title') }}</h2>
                    <form method="POST" action="{{ route('fittings.store', $customer) }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="method" value="camera">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <x-input-label for="pd_binocular" :value="__('ui.fitting.pd_binocular')" />
                                <x-text-input id="pd_binocular" name="pd_binocular" type="number" step="0.1" min="40" max="90" class="mt-1 block w-full" :value="old('pd_binocular')" />
                                <x-input-error :messages="$errors->get('pd_binocular')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="pd_mono_od" :value="__('ui.fitting.pd_mono_od')" />
                                <x-text-input id="pd_mono_od" name="pd_mono_od" type="number" step="0.1" min="15" max="50" class="mt-1 block w-full" :value="old('pd_mono_od')" />
                            </div>
                            <div>
                                <x-input-label for="pd_mono_os" :value="__('ui.fitting.pd_mono_os')" />
                                <x-text-input id="pd_mono_os" name="pd_mono_os" type="number" step="0.1" min="15" max="50" class="mt-1 block w-full" :value="old('pd_mono_os')" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <x-input-label for="face_width" :value="__('ui.fitting.face_width')" />
                                <x-text-input id="face_width" name="face_width" type="number" step="0.1" min="90" max="180" class="mt-1 block w-full" :value="old('face_width')" />
                            </div>
                            <div>
                                <x-input-label for="pupil_height_od" :value="__('ui.fitting.pupil_height_od')" />
                                <x-text-input id="pupil_height_od" name="pupil_height_od" type="number" step="0.1" min="5" max="45" class="mt-1 block w-full" :value="old('pupil_height_od')" />
                            </div>
                            <div>
                                <x-input-label for="pupil_height_os" :value="__('ui.fitting.pupil_height_os')" />
                                <x-text-input id="pupil_height_os" name="pupil_height_os" type="number" step="0.1" min="5" max="45" class="mt-1 block w-full" :value="old('pupil_height_os')" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="pd_reference" :value="__('ui.fitting.pd_reference')" />
                            <x-text-input id="pd_reference" name="pd_reference" type="number" step="0.1" min="40" max="90" class="mt-1 block w-full" :value="old('pd_reference')" />
                            <p class="mt-1 text-xs text-gray-400">{{ __('ui.fitting.pd_reference_hint') }}</p>
                        </div>
                        <div>
                            <x-input-label for="notes" :value="__('ui.fitting.notes')" />
                            <x-text-input id="notes" name="notes" class="mt-1 block w-full" :value="old('notes')" maxlength="1000" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>{{ __('ui.fitting.save') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="px-6 pt-5 font-semibold text-gray-800">{{ __('ui.fitting.history') }}</h2>
                <table class="mt-3 min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.fitting.col_date') }}</th>
                            <th class="px-4 py-3 font-medium">PD</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.fitting.col_mono') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.fitting.face_width') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.fitting.size') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.fitting.pd_reference') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.fitting.col_by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 tnum">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 tnum">{{ $row->pd_binocular ?? '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $row->pd_mono_od ?? '—' }} / {{ $row->pd_mono_os ?? '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $row->face_width ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row->frame_size_recommendation ?? '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $row->pd_reference ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row->measurer->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.fitting.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script type="module">
import { FaceLandmarker, FilesetResolver } from "{{ asset('vendor/mediapipe/vision_bundle.mjs') }}";
const IRIS_MM = 11.7;
const video = document.getElementById('cam');
const statusEl = document.getElementById('camStatus');
const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
const median = (arr) => { const s = [...arr].sort((x, y) => x - y); return s[Math.floor(s.length / 2)]; };
let samples = [];
let latest = null;
try {
    const vision = await FilesetResolver.forVisionTasks("{{ asset('vendor/mediapipe/wasm') }}");
    const landmarker = await FaceLandmarker.createFromOptions(vision, {
        baseOptions: { modelAssetPath: "{{ asset('vendor/mediapipe/face_landmarker.task') }}", delegate: "GPU" },
        runningMode: "VIDEO", numFaces: 1,
    });
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user", width: 1280, height: 720 } });
    video.srcObject = stream;
    await video.play();
    statusEl.textContent = @json(__('ui.fitting.ready'));
    let lastTs = -1;
    const loop = () => {
        if (video.currentTime !== lastTs) {
            lastTs = video.currentTime;
            const res = landmarker.detectForVideo(video, performance.now());
            const lm = res.faceLandmarks && res.faceLandmarks[0];
            if (lm) {
                const irisL = (dist(lm[469], lm[471]) + dist(lm[470], lm[472])) / 2;
                const irisR = (dist(lm[474], lm[476]) + dist(lm[475], lm[477])) / 2;
                const irisPx = (irisL + irisR) / 2;
                if (irisPx > 0.004) {
                    const w = video.videoWidth;
                    const pxPerMm = irisPx * w / IRIS_MM;
                    const bridge = lm[168];
                    const pd = dist(lm[468], lm[473]) * w / pxPerMm;
                    const od = dist(bridge, lm[473]) * w / pxPerMm;
                    const os = dist(bridge, lm[468]) * w / pxPerMm;
                    const fw = dist(lm[234], lm[454]) * w / pxPerMm;
                    if (pd > 45 && pd < 85) {
                        samples.push({ pd, od, os, fw });
                        if (samples.length > 90) samples.shift();
                        if (samples.length >= 15) {
                            latest = { pd: median(samples.map(s => s.pd)), od: median(samples.map(s => s.od)), os: median(samples.map(s => s.os)), fw: median(samples.map(s => s.fw)) };
                            document.getElementById('livePd').textContent = latest.pd.toFixed(1);
                            document.getElementById('liveFw').textContent = latest.fw.toFixed(0);
                            document.getElementById('liveSize').textContent = latest.fw <= 132 ? 'S' : (latest.fw <= 141 ? 'M' : 'L');
                        }
                    }
                }
            }
        }
        requestAnimationFrame(loop);
    };
    loop();
    document.getElementById('useReading').addEventListener('click', () => {
        if (!latest) return;
        document.getElementById('pd_binocular').value = latest.pd.toFixed(1);
        document.getElementById('pd_mono_od').value = latest.od.toFixed(1);
        document.getElementById('pd_mono_os').value = latest.os.toFixed(1);
        document.getElementById('face_width').value = latest.fw.toFixed(1);
    });
} catch (e) {
    statusEl.textContent = @json(__('ui.fitting.camera_failed'));
}
</script>
</x-app-layout>
