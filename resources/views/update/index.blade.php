@extends('install.layout')

@section('title', __('Check for Update'))

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">{{ __('Software Update') }}</h2>

    <div class="rounded-2xl border p-4 mb-6 text-sm" style="border-color: {{ $color }}; background: {{ $color }}18;">
        <p class="font-medium" style="color: {{ $color }};">{{ $message }}</p>
        @if($whatsNew)
            <div class="mt-3 pt-3 border-t text-slate-600" style="border-color: {{ $color }}40;">
                <p class="font-medium text-slate-700 mb-1">{{ __('What\'s new') }}:</p>
                <div class="whitespace-pre-wrap">{{ $whatsNew }}</div>
            </div>
        @endif
    </div>

    @if($updateUrl)
        {{-- Step 1: Download only — shown first --}}
        <div id="step-download" class="mb-4">
            <form id="update-form" action="{{ $updateUrl }}" method="POST" class="inline">
                @csrf
                <button type="submit" id="btn-update" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed">
                    {{ __('Download Update') }}
                </button>
            </form>
            <p class="text-slate-500 text-xs mt-2">{{ __('Do not close this page during download.') }}</p>

            {{-- Download progress --}}
            <div id="download-progress" class="mt-4 hidden" role="status" aria-live="polite">
                <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                    <div class="flex items-start gap-3 mb-3">
                        <svg class="w-5 h-5 text-indigo-600 mt-0.5 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div>
                            <p id="download-progress-title" class="text-sm font-medium text-indigo-900">{{ __('Downloading update...') }}</p>
                            <p id="download-progress-hint" class="text-xs text-indigo-700/80 mt-1">{{ __('Please wait until the download completes. Do not close or refresh this page.') }}</p>
                        </div>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-indigo-100 overflow-hidden">
                        <div id="download-progress-bar" class="h-full rounded-full bg-indigo-600 transition-all duration-500 ease-out" style="width: 0%;"></div>
                    </div>
                    <p id="download-progress-percent" class="text-xs text-indigo-700 mt-2 text-right font-medium">0%</p>
                </div>
            </div>
        </div>
        {{-- Step 2: Install only — shown after download completes --}}
        <div id="step-install" class="mb-4" style="display: none;">
            <p class="text-slate-600 text-sm mb-2">{{ __('Downloaded successfully.') }} {{ __('Click the button below to install.') }}</p>
            <form id="install-form" action="{{ route('update.install') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="install_token" id="install-token-input" value="">
                <button type="submit" id="btn-install" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 disabled:opacity-60 disabled:cursor-not-allowed">
                    {{ __('Install Update') }}
                </button>
            </form>
            <p class="text-slate-500 text-xs mt-2">{{ __('Do not close this page during installation.') }}</p>

            {{-- Install progress --}}
            <div id="install-progress" class="mt-4 hidden" role="status" aria-live="polite">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div>
                            <p id="install-progress-title" class="text-sm font-medium text-emerald-900">{{ __('Installing update...') }}</p>
                            <p id="install-progress-hint" class="text-xs text-emerald-700/80 mt-1">{{ __('Please wait until installation completes. Do not close or refresh this page.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <p class="text-slate-500 text-xs mt-6">
        <a href="{{ url('/') }}" class="text-indigo-600 hover:underline">← {{ __('Back to application') }}</a>
        &nbsp;|&nbsp;
        <a href="{{ route('update.verification') }}" class="text-indigo-600 hover:underline">{{ __('Verify again') }}</a>
        @if($lpIsBd ?? false)
        &nbsp;|&nbsp;
        <a href="{{ route('update.upgrade-license') }}" class="text-indigo-600 hover:underline">{{ __('Upgrade License') }}</a>
        @endif
    </p>

    @if($updateUrl)
    <script>
        (function() {
            var form = document.getElementById('update-form');
            var btn = document.getElementById('btn-update');
            var progressWrap = document.getElementById('download-progress');
            var progressBar = document.getElementById('download-progress-bar');
            var progressPercent = document.getElementById('download-progress-percent');
            var progressTitle = document.getElementById('download-progress-title');
            var progressHint = document.getElementById('download-progress-hint');
            var progressTimer = null;
            var currentProgress = 0;

            var labels = {
                downloading: @json(__('Downloading update...')),
                extracting: @json(__('Extracting update package...')),
                almostDone: @json(__('Almost done, please wait...')),
                waitHint: @json(__('Please wait until the download completes. Do not close or refresh this page.')),
                failed: @json(__('Download failed.'))
            };

            function setProgress(value) {
                currentProgress = Math.min(100, Math.max(0, value));
                progressBar.style.width = currentProgress + '%';
                progressPercent.textContent = Math.round(currentProgress) + '%';
            }

            function startProgressSimulation() {
                setProgress(5);
                progressWrap.classList.remove('hidden');
                progressTitle.textContent = labels.downloading;
                progressHint.textContent = labels.waitHint;

                progressTimer = setInterval(function() {
                    if (currentProgress >= 90) {
                        progressTitle.textContent = labels.almostDone;
                        return;
                    }

                    if (currentProgress >= 55) {
                        progressTitle.textContent = labels.extracting;
                    }

                    var step = currentProgress < 30 ? 4 : (currentProgress < 60 ? 2 : 1);
                    setProgress(currentProgress + step);
                }, 600);
            }

            function stopProgressSimulation() {
                if (progressTimer) {
                    clearInterval(progressTimer);
                    progressTimer = null;
                }
            }

            function resetDownloadUi() {
                stopProgressSimulation();
                progressWrap.classList.add('hidden');
                setProgress(0);
                btn.disabled = false;
                btn.textContent = @json(__('Download Update'));
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                btn.disabled = true;
                btn.textContent = @json(__('Downloading...'));
                startProgressSimulation();

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.status === 'success') {
                        stopProgressSimulation();
                        setProgress(100);
                        progressTitle.textContent = @json(__('Download complete!'));
                        progressHint.textContent = @json(__('Preparing install step...'));

                        setTimeout(function() {
                            document.getElementById('step-download').style.display = 'none';
                            document.getElementById('step-install').style.display = 'block';
                            if (data.install_token) {
                                document.getElementById('install-token-input').value = data.install_token;
                            }
                        }, 400);
                    } else {
                        resetDownloadUi();
                        alert(data.message || labels.failed);
                    }
                }).catch(function() {
                    resetDownloadUi();
                    alert(labels.failed);
                });
            });

            var installForm = document.getElementById('install-form');
            var installBtn = document.getElementById('btn-install');
            var installProgress = document.getElementById('install-progress');
            var installProgressTitle = document.getElementById('install-progress-title');
            var installProgressHint = document.getElementById('install-progress-hint');

            var installLabels = {
                installing: @json(__('Installing update...')),
                waitHint: @json(__('Please wait until installation completes. Do not close or refresh this page.')),
                redirecting: @json(__('Installation complete! Redirecting...')),
                failed: @json(__('Installation failed.'))
            };

            function resetInstallUi() {
                installProgress.classList.add('hidden');
                installBtn.disabled = false;
                installBtn.textContent = @json(__('Install Update'));
            }

            installForm.addEventListener('submit', function(e) {
                e.preventDefault();
                installBtn.disabled = true;
                installBtn.textContent = @json(__('Installing...'));
                installProgress.classList.remove('hidden');
                installProgressTitle.textContent = installLabels.installing;
                installProgressHint.textContent = installLabels.waitHint;

                fetch(installForm.action, {
                    method: 'POST',
                    body: new FormData(installForm),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.status === 'success') {
                        installProgressTitle.textContent = installLabels.redirecting;
                        installProgressHint.textContent = installLabels.waitHint;
                        window.location.href = data.action || @json(route('update.success'));
                    } else {
                        resetInstallUi();
                        alert(data.message || installLabels.failed);
                    }
                }).catch(function() {
                    resetInstallUi();
                    alert(installLabels.failed);
                });
            });
        })();
    </script>
    @endif
@endsection
