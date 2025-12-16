<?php
/**
 * Pugo - Build Hugo Action
 * 
 * Builds the Hugo static site.
 */

namespace Pugo\Actions\Build;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class BuildHugoAction
{
    public function __construct(
        private string $hugoRoot,
        private string $hugoCommand = 'hugo --minify'
    ) {}

    /**
     * Build the Hugo site
     * 
     * @param bool $runPagefind Whether to run Pagefind after build
     */
    public function handle(bool $runPagefind = true): ActionResult
    {
        $output = [];
        $returnCode = 0;
        $publicDir = $this->hugoRoot . '/public';

        try {
            // Ensure public directory exists and is writable
            if (is_dir($publicDir)) {
                exec('chown -R www-data:www-data ' . escapeshellarg($publicDir) . ' 2>&1');
                exec('chmod -R 775 ' . escapeshellarg($publicDir) . ' 2>&1');
            }

            // Change to Hugo root and build
            $command = 'cd ' . escapeshellarg($this->hugoRoot) . ' && ' . $this->hugoCommand . ' 2>&1';
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                return ActionResult::failure(
                    error: 'Hugo build failed',
                    data: ['output' => implode("\n", $output)]
                );
            }

            // Fix permissions after build
            exec('chown -R www-data:www-data ' . escapeshellarg($publicDir) . ' 2>&1');
            exec('chmod -R 775 ' . escapeshellarg($publicDir) . ' 2>&1');

            // Run Pagefind if requested
            if ($runPagefind) {
                $pagefindOutput = [];
                $pagefindCode = 0;
                
                exec('cd ' . escapeshellarg($this->hugoRoot) . ' && pagefind --site public 2>&1', $pagefindOutput, $pagefindCode);
                
                $output[] = '';
                $output[] = '--- Pagefind ---';
                $output = array_merge($output, $pagefindOutput);

                if ($pagefindCode !== 0) {
                    $output[] = '(Pagefind had warnings but Hugo build succeeded)';
                }
            }

            return ActionResult::success(
                message: 'Site built successfully',
                data: ['output' => implode("\n", $output)]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Build error: ' . $e->getMessage());
        }
    }
}

