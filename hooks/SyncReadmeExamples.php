<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator\Hooks;

use CaptainHook\App\Config;
use CaptainHook\App\Config\Action as ActionConfig;
use CaptainHook\App\Console\IO;
use CaptainHook\App\Hook\Action;
use SebastianFeldmann\Git\Repository;

final class SyncReadmeExamples implements Action
{
    /**
     * Execute the action to sync README.md with example files
     */
    public function execute(Config $config, IO $io, Repository $repository, ActionConfig $action) : void
    {
        $readmePath = $repository->getRoot() . '/README.md';

        if ( ! file_exists($readmePath)) {
            $io->write('README.md not found, skipping sync', true, IO::VERBOSE);

            return;
        }

        $io->write('Syncing README.md with example files...', true, IO::VERBOSE);

        $readme = file_get_contents($readmePath);
        $updatedReadme = $this->syncReadmeWithExamples($readme, $repository->getRoot(), $io);

        if ($readme !== $updatedReadme) {
            file_put_contents($readmePath, $updatedReadme);
            $io->write('<info>✓ README.md has been synced with example files</info>');

            // Stage the README changes
            exec('git add README.md');
        } else {
            $io->write('<info>✓ README.md is already in sync</info>');
        }
    }

    /**
     * Sync README content with example files
     */
    private function syncReadmeWithExamples(string $readme, string $repositoryRoot, IO $io) : string
    {
        $lines = explode("\n", $readme);
        $result = [];
        $i = 0;

        while ($i < count($lines)) {
            $line = $lines[$i];

            // Check for source comment
            if (preg_match('/^<!-- source: (.+) -->$/', trim($line), $matches)) {
                $sourceFile = $matches[1];
                $result[] = $line; // Keep the source comment
                $i++;

                // Process the code block
                if ($i < count($lines) && preg_match('/^```php\s*$/', $lines[$i])) {
                    $result[] = $lines[$i]; // Keep ```php
                    $i++;

                    // Skip old code content until closing ```
                    while ($i < count($lines) && $lines[$i] !== '```') {
                        $i++;
                    }

                    // Insert new code from source file
                    $code = $this->getExampleCode($sourceFile, $repositoryRoot, $io);

                    if ($code !== null) {
                        // Add code lines without trailing newline on last line
                        $codeLines = explode("\n", rtrim($code));
                        foreach ($codeLines as $codeLine) {
                            $result[] = $codeLine;
                        }
                    }

                    if ($i < count($lines) && $lines[$i] === '```') {
                        $result[] = $lines[$i]; // Keep closing ```
                        $i++;
                    }
                }
            }
            // Check for output comment
            elseif (preg_match('/^<!-- output: (.+) -->$/', trim($line), $matches)) {
                $sourceFile = $matches[1];
                $result[] = $line; // Keep the output comment
                $i++;

                // Process the output code block
                if ($i < count($lines) && preg_match('/^```php\s*$/', $lines[$i])) {
                    $result[] = $lines[$i]; // Keep ```php
                    $i++;

                    // Skip old output until closing ```
                    while ($i < count($lines) && $lines[$i] !== '```') {
                        $i++;
                    }

                    // Insert new output from executing the file
                    $output = $this->executeExample($sourceFile, $repositoryRoot, $io);

                    if ($output !== null) {
                        $outputLines = explode("\n", rtrim($output));
                        foreach ($outputLines as $outputLine) {
                            $result[] = $outputLine;
                        }
                    }

                    if ($i < count($lines) && $lines[$i] === '```') {
                        $result[] = $lines[$i]; // Keep closing ```
                        $i++;
                    }
                }
            } else {
                $result[] = $line;
                $i++;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Get code from example file with path adjustments
     */
    private function getExampleCode(string $sourceFile, string $repositoryRoot, IO $io) : ?string
    {
        $fullPath = $repositoryRoot . '/' . $sourceFile;

        if ( ! file_exists($fullPath)) {
            $io->write(sprintf('<warning>Source file not found: %s</warning>', $sourceFile), true, IO::VERBOSE);

            return null;
        }

        $code = file_get_contents($fullPath);

        // Replace ../vendor/autoload.php with vendor/autoload.php for README display
        $code = str_replace(
            ["include '../vendor/autoload.php'", "require '../vendor/autoload.php'"],
            ["include 'vendor/autoload.php'", "require 'vendor/autoload.php'"],
            $code,
        );

        // Remove opening <?php tag and initial empty lines
        $lines = explode("\n", $code);
        $result = [];
        $foundStart = false;

        foreach ($lines as $line) {
            if ( ! $foundStart) {
                if (trim($line) === '<?php') {
                    $result[] = '<?php';
                    $foundStart = true;
                } elseif (trim($line) !== '') {
                    $foundStart = true;
                    $result[] = $line;
                }
            } else {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Execute example file and capture output
     */
    private function executeExample(string $sourceFile, string $repositoryRoot, IO $io) : ?string
    {
        $fullPath = $repositoryRoot . '/' . $sourceFile;

        if ( ! file_exists($fullPath)) {
            $io->write(sprintf('<warning>Source file not found: %s</warning>', $sourceFile), true, IO::VERBOSE);

            return null;
        }

        // Create temporary file for execution
        $tempFile = tempnam(sys_get_temp_dir(), 'example_');

        try {
            // Copy the file content and ensure it has proper autoload path
            $code = file_get_contents($fullPath);

            // Make sure the code uses absolute path for autoload
            $code = preg_replace(
                "/(include|require|include_once|require_once)\s+['\"]\.\.\/vendor\/autoload\.php['\"]/",
                "$1 '" . $repositoryRoot . "/vendor/autoload.php'",
                $code,
            );

            file_put_contents($tempFile, $code);

            // Execute and capture output
            $command = sprintf('php %s 2>&1', escapeshellarg($tempFile));
            $output = shell_exec($command);

            if ($output === null) {
                $io->write(sprintf('<warning>Failed to execute: %s</warning>', $sourceFile), true, IO::VERBOSE);

                return null;
            }

            // Clean up the output
            return $this->normalizeOutput($output);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Normalize output for clean display
     */
    private function normalizeOutput(string $output) : string
    {
        // Remove trailing whitespace from each line
        $lines = explode("\n", $output);
        $lines = array_map('rtrim', $lines);

        // Remove leading/trailing empty lines
        while ( ! empty($lines) && trim($lines[0]) === '') {
            array_shift($lines);
        }

        while ( ! empty($lines) && trim($lines[count($lines) - 1]) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }
}
