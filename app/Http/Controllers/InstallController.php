<?php

namespace App\Http\Controllers;

use App\Services\InstallerService;
use App\Services\BusinessTypeService;
use App\Services\ModuleUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstallController extends Controller
{
    public function __construct(
        protected InstallerService $installer,
        protected BusinessTypeService $businessTypes,
        protected ModuleUsageService $moduleUsage
    ) {}

    /**
     * 
     */
    public function welcome(): View
    {
        return view('install.welcome');
    }

    /**
     * 
     */
    public function environment(): View|RedirectResponse
    {
        $result = $this->installer->getEnvironmentChecks();
        return view('install.environment', $result);
    }

    /**
     * 
     */
    public function purchase(): View|RedirectResponse
    {
        return view('install.purchase');
    }

    /**
     * 
     */
    public function verPur(Request $request): RedirectResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'username' => ['required', 'string', 'max:255'],
                'purchase_code' => ['required', 'string', 'max:255'],
                'already_installed_get_update' => ['nullable'],
            ],
            [
                'username.required' => 'Username is required.',
                'purchase_code.required' => 'Purchase code is required.',
                'already_installed_get_update.boolean' => 'Already installed get update must be yes or no.',
            ]
        );

        if ($validator->fails()) {
            return redirect()
                ->route('install.purchase')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $alreadyInstalledGetUpdate = $request->boolean('already_installed_get_update');
        $installationUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/') . '/';
        $referer = $request->fullUrl();
        $path = base_path();
        $ip = $request->ip() ?? '';

        try {
            $result = $this->installer->verPur(
                $validated['username'],
                $validated['purchase_code'],
                $installationUrl,
                $ip,
                $referer,
                $path,
                $alreadyInstalledGetUpdate
            );
        } catch (\Throwable $e) {
            Log::channel('single')->error('Install verification error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return redirect()
                ->route('install.purchase')
                ->withInput()
                ->with('error', __('Purchase verification could not be completed. Check your internet connection, then try again. If it keeps failing, verify your Username and Purchase Code or contact support.'));
        }

        if (
            $alreadyInstalledGetUpdate
            && ($this->purchaseResponseIndicatesNotYetInstalled($result['installation_status']))
        ) {
            return redirect()
                ->route('install.purchase')
                ->withInput()
                ->with('error', __('You are trying to update, but your software is not yet installed.'));
        }

        $allowNextStep = $result['success'];
        if (!$result['success'] && $alreadyInstalledGetUpdate) {
            $currentInstalledUrl = $result['current_installed_url'] ?? null;
            $normalizedCurrent = $currentInstalledUrl ? rtrim($currentInstalledUrl, '/') . '/' : '';
            $normalizedInstall = rtrim($installationUrl, '/') . '/';
            if ($currentInstalledUrl && $normalizedCurrent === $normalizedInstall) {
                $allowNextStep = true;
            }
        }

        if (!$allowNextStep) {
            $errorMsg = $result['message'];
            if ($alreadyInstalledGetUpdate && !$result['success']) {
                $currentInstalledUrl = $result['current_installed_url'] ?? null;
                if (!$currentInstalledUrl || rtrim($currentInstalledUrl, '/') . '/' !== rtrim($installationUrl, '/') . '/') {
                    $errorMsg .= ' Installation URL mismatch.';
                }
            }
            return redirect()
                ->route('install.purchase')
                ->withInput()
                ->with('error', $errorMsg);
        }

        $purchasePayload = [
            'username' => $validated['username'],
            'purchase_code' => $validated['purchase_code'],
            'installation_status' => $result['installation_status'],
            'installation_url' => $installationUrl,
            'already_installed_get_update' => $alreadyInstalledGetUpdate,
        ];
        Session::put('install_purchase', $purchasePayload);
        if (!$this->installer->saveInstallPurchaseState($purchasePayload)) {
            Log::channel('single')->warning('Could not write install purchase backup file; database step may require re-verification if the session is lost.', [
                'path' => storage_path('app/install_purchase_state.json'),
            ]);
        }

        return redirect()->route('install.business-type')
            ->with('success', $result['message']);
    }

    public function businessType(): View|RedirectResponse
    {
        $purchase = $this->installer->resolveInstallPurchase();
        if ($purchase === null) {
            return redirect()
                ->route('install.purchase')
                ->with(
                    'error',
                    __('Purchase verification is required before selecting a business type. Verify your purchase code first.')
                );
        }

        $saved = $this->installer->resolveInstallBusinessType();
        $selectedType = old('business_type', $saved['business_type'] ?? '');
        $selectedModules = is_array($saved['modules'] ?? null) ? $saved['modules'] : null;
        $featurePayload = $selectedType !== ''
            ? $this->businessTypes->featurePayload($selectedType, $selectedModules)
            : null;

        return view('install.business-type', [
            'businessTypeOptions' => $this->businessTypes->options(),
            'selectedType' => $selectedType,
            'featurePayload' => $featurePayload,
        ]);
    }

    public function businessTypeFeatures(Request $request)
    {
        $request->validate([
            'type' => ['required', 'string'],
        ]);

        if (! $this->businessTypes->isValid($request->string('type')->toString())) {
            return response()->json(['message' => __('Invalid business type selected.')], 422);
        }

        return response()->json(
            $this->businessTypes->featurePayload($request->string('type')->toString())
        );
    }

    public function storeBusinessType(Request $request): RedirectResponse
    {
        $purchase = $this->installer->resolveInstallPurchase();
        if ($purchase === null) {
            return redirect()
                ->route('install.purchase')
                ->with(
                    'error',
                    __('Purchase verification is required. Complete the Purchase step first.')
                );
        }

        $typeSlugs = array_keys($this->businessTypes->options());
        $validator = Validator::make($request->all(), [
            'business_type' => ['required', 'string', Rule::in($typeSlugs)],
        ], [
            'business_type.required' => 'Please select a business type.',
            'business_type.in' => 'The selected business type is invalid.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('install.business-type')
                ->withErrors($validator)
                ->withInput();
        }

        $businessType = $validator->validated()['business_type'];
        $modules = $this->businessTypes->normalizeSubmittedModules(
            $businessType,
            $request->all()
        );
        $type = $this->businessTypes->types()[$businessType];

        $this->moduleUsage->save(
            $modules,
            $businessType,
            $this->businessTypes->displayLabel($businessType) ?? $type['label'],
            $type['product_types']
        );

        $businessTypePayload = [
            'business_type' => $businessType,
            'business_type_label' => $this->businessTypes->displayLabel($businessType) ?? $type['label'],
            'product_types' => $type['product_types'],
            'modules' => $modules,
        ];
        Session::put('install_business_type', $businessTypePayload);
        $this->installer->saveInstallBusinessTypeState($businessTypePayload);

        return redirect()
            ->route('install.database')
            ->with('success', __('Business type and module preferences saved successfully.'));
    }

    /**
     * 
     */
    public function database(): View|RedirectResponse
    {
        $purchase = $this->installer->resolveInstallPurchase();
        if ($purchase === null) {
            return redirect()
                ->route('install.purchase')
                ->with(
                    'error',
                    __('Purchase verification is required before the database step. Verify your purchase code first (your progress was not found in the browser session or install backup).')
                );
        }

        $businessType = $this->installer->resolveInstallBusinessType();
        if ($businessType === null) {
            return redirect()
                ->route('install.business-type')
                ->with(
                    'error',
                    __('Please select your business type and module preferences before continuing to the database step.')
                );
        }

        $db = Session::get('install_db', []);
        $alreadyInstalledGetUpdate = (bool) ($purchase['already_installed_get_update'] ?? false);
        return view('install.database', [
            'db_host' => $db['db_host'] ?? '127.0.0.1',
            'db_port' => $db['db_port'] ?? '3306',
            'db_username' => $db['db_username'] ?? '',
            'db_password' => $db['db_password'] ?? '',
            'db_name' => $db['db_name'] ?? '',
            'create_db' => $db['create_db'] ?? true,
            'database_install_mode' => old(
                'database_install_mode',
                $db['database_install_mode'] ?? ($alreadyInstalledGetUpdate ? 'upgrade_existing' : 'empty_existing')
            ),
            'already_installed_get_update' => $alreadyInstalledGetUpdate,
        ]);
    }

    /**
     * 
     */
    public function storeDatabase(Request $request): RedirectResponse
    {
        $purchase = $this->installer->resolveInstallPurchase();
        if ($purchase === null) {
            return redirect()
                ->route('install.purchase')
                ->withInput()
                ->with(
                    'error',
                    __('Purchase verification is required. Complete the Purchase step first — we could not restore your verification from the session or install backup file.')
                );
        }

        $businessType = $this->installer->resolveInstallBusinessType();
        if ($businessType === null) {
            return redirect()
                ->route('install.business-type')
                ->withInput()
                ->with(
                    'error',
                    __('Please select your business type and module preferences before continuing to the database step.')
                );
        }

        $alreadyInstalledGetUpdate = (bool) ($purchase['already_installed_get_update'] ?? false);
        $allowedInstallModes = $alreadyInstalledGetUpdate
            ? ['upgrade_existing']
            : ['empty_existing', 'use_existing'];

        $rules = [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['nullable', 'string', 'max:10'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string'],
            'db_name' => ['required', 'string', 'max:255'],
            'create_db' => ['nullable', 'boolean'],
            'database_install_mode' => ['required', 'string', Rule::in($allowedInstallModes)],
        ];

        $attributes = [
            'db_host' => 'Database Host',
            'db_port' => 'Port',
            'db_username' => 'Database Username',
            'db_password' => 'Database Password',
            'db_name' => 'Database Name',
            'create_db' => 'Create database if not exists',
            'database_install_mode' => 'Installation mode',
        ];

        $messages = [
            'db_host.required' => 'Database Host is required.',
            'db_host.max' => 'Database Host must not exceed 255 characters.',
            'db_port.max' => 'Port must not exceed 10 characters.',
            'db_username.required' => 'Database Username is required.',
            'db_username.max' => 'Database Username must not exceed 255 characters.',
            'db_name.required' => 'Database Name is required.',
            'db_name.max' => 'Database Name must not exceed 255 characters.',
            'create_db.boolean' => 'Create database option must be yes or no.',
            'database_install_mode.required' => 'Please choose how to install against the database.',
            'database_install_mode.in' => 'Invalid installation mode selected.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return redirect()
                ->route('install.database')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $host = $validated['db_host'];
        $port = $validated['db_port'] ?? '3306';
        $username = $validated['db_username'];
        $password = $validated['db_password'] ?? '';
        $database = $validated['db_name'];
        $createDb = $request->boolean('create_db');
        $databaseInstallMode = $validated['database_install_mode'];

        try {
            $result = $this->installer->testDatabaseConnection(
                $host,
                $port,
                $username,
                $password,
                $database,
                $createDb
            );
        } catch (\Throwable $e) {
            Log::channel('single')->error('Install database test error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return redirect()
                ->route('install.database')
                ->withInput()
                ->with('error', __('Could not test the database connection due to an unexpected error. Check your database server, then try again.'));
        }

        if (!$result['success']) {
            return redirect()
                ->route('install.database')
                ->withInput()
                ->with('error', $result['message']);
        }

        if ($databaseInstallMode === 'empty_existing') {
            try {
                $dropResult = $this->installer->dropAllTablesInDatabase(
                    $host,
                    $port,
                    $username,
                    $password,
                    $database
                );
            } catch (\Throwable $e) {
                Log::channel('single')->error('Install empty database error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return redirect()
                    ->route('install.database')
                    ->withInput()
                    ->with('error', __('Could not empty the database due to an unexpected error. Check that your MySQL user has DROP privileges.'));
            }

            if (!$dropResult['success']) {
                return redirect()
                    ->route('install.database')
                    ->withInput()
                    ->with('error', $dropResult['message']);
            }
        }

        $installDb = [
            'db_host' => $host,
            'db_port' => $port,
            'db_username' => $username,
            'db_password' => $password,
            'db_name' => $database,
            'create_db' => $createDb,
            'database_install_mode' => $databaseInstallMode,
        ];
        Session::put('install_db', $installDb);
        $this->installer->saveInstallDbState($installDb);

        $appKey = 'base64:' . $this->installer->generateAppKey();
        $appUrl = rtrim(
            trim($request->getSchemeAndHttpHost() . $request->getBasePath(), " \t\n\r\0\x0B\"'"),
            '/'
        );

        $writeOk = $this->installer->writeEnv([
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $database,
            'db_username' => $username,
            'db_password' => $password,
            'app_url' => $appUrl,
            'app_key' => $appKey,
        ]);

        if (!$writeOk) {
            return redirect()
                ->route('install.database')
                ->withInput()
                ->with('error', __('Could not write the .env file. Make sure the project root folder is writable by the web server, then try again.'));
        }

        if ($databaseInstallMode === 'use_existing') {
            try {
                $importResult = $this->installer->registerInstallationOnly(
                    $purchase['username'],
                    $purchase['purchase_code'],
                    $purchase['installation_url'] ?? $appUrl . '/',
                    $appKey,
                    $purchase['already_installed_get_update'] ?? false
                );
            } catch (\Throwable $e) {
                Log::channel('single')->error('Install registration (existing DB) error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return redirect()
                    ->route('install.database')
                    ->withInput()
                    ->with('error', __('Could not register this installation URL with the license server. Check your internet connection and try again.'));
            }

            if (!$importResult['success']) {
                return redirect()
                    ->route('install.database')
                    ->withInput()
                    ->with('error', $importResult['message']);
            }

            $serverMsg = trim((string) ($importResult['message'] ?? ''));
            $importResult['message'] = $serverMsg !== ''
                ? $serverMsg . ' Saved using your existing database (no import).'
                : 'Saved using your existing database (no import).';
        } else {
            try {
                $importResult = $this->installer->connectDatabase(
                    [
                        'db_host' => $host,
                        'db_port' => $port,
                        'db_name' => $database,
                        'db_username' => $username,
                        'db_password' => $password,
                    ],
                    $purchase['username'],
                    $purchase['purchase_code'],
                    $purchase['installation_url'] ?? $appUrl . '/',
                    $appKey,
                    $purchase['already_installed_get_update'] ?? false
                );
            } catch (\Throwable $e) {
                Log::channel('single')->error('Install database import error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return redirect()
                    ->route('install.database')
                    ->withInput()
                    ->with('error', __('Database import failed due to an unexpected error. Check your connection and try again, or use “Install on existing database” if the schema is already loaded.'));
            }

            if (!$importResult['success']) {
                return redirect()
                    ->route('install.database')
                    ->withInput()
                    ->with('error', $importResult['message']);
            }
        }

        $this->installer->setInstalled();
        Config::set('app.key', $appKey);
        App::forgetInstance('encrypter');
        $this->installer->secFileW(
            $purchase['username'],
            $purchase['purchase_code'],
            $appUrl . '/',
            '11.2',
            $appKey
        );
        $this->installer->clearInstallDbState();
        $alreadyInstalledGetUpdate = $purchase['already_installed_get_update'] ?? false;
        Session::forget(['install_db', 'install_app_key', 'install_purchase', 'install_business_type']);

        return redirect()->route('install.complete', [
            'already_installed_get_update' => $alreadyInstalledGetUpdate ? '1' : '0',
            'database_install_mode' => $databaseInstallMode,
        ])->with('success', $importResult['message']);
    }


    /**
     * 
     */
    public function uninstallComplete(Request $request): View|RedirectResponse
    {
        if ($request->query('uninstalled') !== '1') {
            return redirect()->route('install.welcome');
        }

        return view('install.uninstall_complete', [
            'install_again_url' => route('install.welcome'),
        ]);
    }

    public function complete(): View|RedirectResponse
    {
        if (!$this->installer->isInstalled()) {
            return redirect()
                ->route('install.welcome')
                ->with('error', __('Installation is not marked as complete yet. Continue from Welcome in the installer until all steps finish successfully.'));
        }

        $appUrl = rtrim(request()->getSchemeAndHttpHost() . request()->getBasePath(), '/');

        return view('install.complete', [
            'login_url' => $appUrl,
            'login_email' => 'admin@doorsoft.co',
            'login_password' => '123456',
            'already_installed_get_update' => request()->query('already_installed_get_update') === '1',
            'database_install_mode' => request()->query('database_install_mode', 'empty_existing'),
        ]);
    }

    /**
     * 
     */
    public function uninstall(): View
    {
        $status = session('uninstall_status');
        $message = session('uninstall_message');
        $color = session('uninstall_color', 'red');

        return view('install.uninstall', [
            'status' => $status,
            'message' => $message,
            'color' => $color,
        ]);
    }

    /**
     * 
     */
    public function uninstallLicense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'purchase_code' => ['required', 'string', 'max:100'],
            'owner' => ['nullable', 'string', 'max:100'],
            'current_installation_url' => ['nullable', 'string', 'max:500'],
            'new_installation_url' => ['nullable', 'string', 'max:500'],
            'action_type' => ['nullable', 'string', 'in:uninstall,transfer'],
            'base_url_install' => ['nullable', 'string', 'max:500'],
        ], [
            'username.required' => __('Username is required.'),
            'purchase_code.required' => __('Purchase code is required.'),
        ]);

        $installationUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/') . '/';
        $referer = $request->fullUrl();
        $path = base_path();
        $ip = $request->ip() ?? '';

        $params = [
            'username' => $validated['username'],
            'purchase_code' => $validated['purchase_code'],
            'owner' => $validated['owner'] ?? 'doorsoftco',
            'current_installation_url' => $validated['current_installation_url'] ?? $installationUrl,
            'new_installation_url' => $validated['new_installation_url'] ?? '',
            'action_type' => $validated['action_type'] ?? 'uninstall',
            'base_url_install' => $validated['base_url_install'] ?? $installationUrl,
        ];

        try {
            $result = $this->installer->uninstallLicense($params, $ip, $referer, $path);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Uninstall license error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return redirect()
                ->route('uninstall.license')
                ->withInput()
                ->with('uninstall_status', 'error')
                ->with('uninstall_message', 'An error occurred. Please try again.')
                ->with('uninstall_color', 'red')
                ->with('error', __('An error occurred. Please try again.'));
        }

        if ($result['success']) {
            $this->installer->remSecFi();
            $this->installer->prepareEnvAfterUninstall();

            return redirect()
                ->route('install.uninstall_complete', ['uninstalled' => '1']);
        }

        return redirect()
            ->route('uninstall.license')
            ->withInput()
            ->with('uninstall_status', 'error')
            ->with('uninstall_message', $result['message'])
            ->with('uninstall_color', 'red')
            ->with('error', $result['message']);
    }

    /**
     *
     */
    private function purchaseResponseIndicatesNotYetInstalled($installationStatus)
    {
        $normalized = strtolower(trim((string) $installationStatus));

        return $normalized == 'uninstalled' || $normalized == 'not installed';
    }
}
