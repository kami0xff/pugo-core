<?php
/**
 * Pugo - Publish Action
 * 
 * Commits changes and pushes to trigger CI/CD pipeline.
 */

namespace Pugo\Actions\Build;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class PublishAction
{
    public function __construct(
        private string $hugoRoot,
        private string $gitUserName = 'Pugo Admin',
        private string $gitUserEmail = 'admin@pugo.local'
    ) {}

    /**
     * Commit and push changes
     * 
     * @param string $message Commit message
     */
    public function handle(string $message = 'Content update from Pugo'): ActionResult
    {
        $output = [];

        try {
            // Configure git user
            $this->exec("git config user.name " . escapeshellarg($this->gitUserName));
            $this->exec("git config user.email " . escapeshellarg($this->gitUserEmail));

            // Check for changes
            $status = $this->exec("git status --porcelain");
            
            if (empty(trim(implode("\n", $status)))) {
                return ActionResult::success(
                    message: 'No changes to publish',
                    data: ['status' => 'no_changes']
                );
            }

            // Stage all changes
            $output[] = '--- Staging changes ---';
            $stageOutput = $this->exec("git add -A");
            $output = array_merge($output, $stageOutput);

            // Commit
            $output[] = '';
            $output[] = '--- Committing ---';
            $commitOutput = $this->exec("git commit -m " . escapeshellarg($message));
            $output = array_merge($output, $commitOutput);

            // Push
            $output[] = '';
            $output[] = '--- Pushing ---';
            $pushOutput = $this->exec("git push 2>&1");
            $output = array_merge($output, $pushOutput);

            return ActionResult::success(
                message: 'Changes published successfully',
                data: [
                    'output' => implode("\n", $output),
                    'status' => 'published'
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure(
                error: 'Publish failed: ' . $e->getMessage(),
                data: ['output' => implode("\n", $output)]
            );
        }
    }

    /**
     * Get current git status
     */
    public function getStatus(): ActionResult
    {
        try {
            $statusOutput = $this->exec("git status --porcelain");
            
            $changes = [];
            foreach ($statusOutput as $line) {
                if (trim($line)) {
                    $status = substr($line, 0, 2);
                    $file = trim(substr($line, 3));
                    $changes[] = [
                        'status' => trim($status),
                        'file' => $file
                    ];
                }
            }

            // Get current branch
            $branchOutput = $this->exec("git rev-parse --abbrev-ref HEAD");
            $branch = trim(implode('', $branchOutput));

            // Get last commit
            $lastCommitOutput = $this->exec("git log -1 --format='%h - %s (%ar)'");
            $lastCommit = trim(implode('', $lastCommitOutput));

            return ActionResult::success(
                message: count($changes) . ' changed file(s)',
                data: [
                    'changes' => $changes,
                    'branch' => $branch,
                    'last_commit' => $lastCommit,
                    'has_changes' => count($changes) > 0
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Could not get git status: ' . $e->getMessage());
        }
    }

    private function exec(string $command): array
    {
        $output = [];
        $returnCode = 0;
        
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && ' . $command, $output, $returnCode);
        
        if ($returnCode !== 0 && !str_contains($command, 'status')) {
            throw new \RuntimeException("Command failed: {$command}\n" . implode("\n", $output));
        }
        
        return $output;
    }
}

