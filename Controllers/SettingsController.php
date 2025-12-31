<?php
/**
 * SettingsController - Site settings and deployment
 */

namespace Pugo\Controllers;

class SettingsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/deploy.php';
    }

    /**
     * Settings page
     */
    public function index(): void
    {
        $this->requireAuth();

        $deployStatus = get_deploy_status();
        $buildResult = null;
        $deployResult = null;
        $setupResult = null;

        // Handle POST actions
        if ($this->isPost()) {
            $this->validateCsrf();
            $action = $this->post('action', '');

            switch ($action) {
                case 'build':
                    $buildResult = build_hugo();
                    break;

                case 'deploy':
                    $buildResult = build_hugo();
                    if ($buildResult['success']) {
                        $message = $this->post('commit_message', 'Deploy: ' . date('Y-m-d H:i'));
                        $deployResult = deploy_site($message);
                        $deployStatus = get_deploy_status();
                    } else {
                        $deployResult = ['success' => false, 'output' => 'Build failed. Cannot deploy.'];
                    }
                    break;

                case 'setup_deploy':
                    $remoteUrl = trim($this->post('remote_url', ''));
                    if (!empty($remoteUrl)) {
                        $setupResult = init_deploy_repo($remoteUrl);
                        $deployStatus = get_deploy_status();
                    }
                    break;
            }
        }

        $this->render('settings/index', [
            'pageTitle' => 'Settings',
            'deployStatus' => $deployStatus,
            'buildResult' => $buildResult,
            'deployResult' => $deployResult,
            'setupResult' => $setupResult,
        ]);
    }
}

