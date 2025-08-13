<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator\Hooks;

use CaptainHook\App\Config;
use CaptainHook\App\Config\Action as ActionConfig;
use CaptainHook\App\Console\IO;
use CaptainHook\App\Hook\Action;
use RuntimeException;
use SebastianFeldmann\Git\Repository;

final class ValidateReadmeCodeSnippets implements Action
{
    /**
     * Execute the action to validate README.md code snippets
     */
    public function execute(Config $config, IO $io, Repository $repository, ActionConfig $action) : void
    {
        $readmePath = $repository->getRoot() . '/README.md';

        if ( ! file_exists($readmePath)) {
            $io->write('README.md not found, skipping validation', true, IO::VERBOSE);

            return;
        }

        $io->write('Validating README.md code snippets...', true, IO::VERBOSE);

        $readme = file_get_contents($readmePath);
        $snippets = $this->extractCodeSnippets($readme);

        if (empty($snippets)) {
            $io->write('No PHP code snippets found in README.md', true, IO::VERBOSE);

            return;
        }

        $io->write(sprintf('Found %d PHP code snippet(s) to validate', count($snippets)), true, IO::VERBOSE);

        foreach ($snippets as $index => $snippet) {
            $io->write(sprintf('Validating snippet #%d...', $index + 1), true, IO::VERBOSE);

            if ($snippet['type'] === 'input') {
                $this->validateInputOutputPair($snippet, $snippets[$index + 1] ?? null, $io, $repository);
            }
        }

        $io->write('<info>✓ All README.md code snippets are valid!</info>');
    }

    /**
     * Extract code snippets from README content
     *
     * @return array<int, array{type: string, content: string, line: int}>
     */
    private function extractCodeSnippets(string $content) : array
    {
        $snippets = [];
        $lines = explode("\n", $content);
        $inCodeBlock = false;
        $currentSnippet = '';
        $snippetStartLine = 0;
        $blockType = '';
        $lastHeading = '';

        foreach ($lines as $lineNumber => $line) {
            // Track headings to identify output sections
            if (preg_match('/^###?\s+(.+)$/i', $line, $matches)) {
                $lastHeading = strtolower(trim($matches[1]));
            }

            if (preg_match('/^```php\s*$/', $line)) {
                $inCodeBlock = true;
                $currentSnippet = '';
                $snippetStartLine = $lineNumber + 1;
                $blockType = ($lastHeading === 'output') ? 'output' : 'input';
            } elseif ($inCodeBlock && $line === '```') {
                $inCodeBlock = false;

                if ( ! empty(trim($currentSnippet))) {
                    $snippets[] = [
                        'type' => $blockType,
                        'content' => $currentSnippet,
                        'line' => $snippetStartLine,
                    ];
                }
            } elseif ($inCodeBlock) {
                $currentSnippet .= $line . "\n";
            }
        }

        return $snippets;
    }

    /**
     * Validate an input/output pair of code snippets
     */
    private function validateInputOutputPair(?array $input, ?array $output, IO $io, Repository $repository) : void
    {
        if ( ! $input || ! $output || $output['type'] !== 'output') {
            return;
        }

        // Create temporary file to execute the input code
        $tempFile = tempnam(sys_get_temp_dir(), 'readme_snippet_');
        $tempOutput = $tempFile . '.out';

        try {
            // Prepare the input code for execution
            $executableCode = $this->prepareExecutableCode($input['content'], $repository->getRoot());
            file_put_contents($tempFile, $executableCode);

            // Execute the code and capture output
            $command = sprintf('php %s 2>&1', escapeshellarg($tempFile));
            $actualOutput = shell_exec($command);

            if ($actualOutput === null) {
                throw new RuntimeException('Failed to execute code snippet');
            }

            // Normalize outputs for comparison
            $expectedOutput = $this->normalizeOutput($output['content']);
            $actualOutput = $this->normalizeOutput($actualOutput);

            if ($expectedOutput !== $actualOutput) {
                $io->write('<error>✗ Code snippet output mismatch!</error>');
                $io->write(sprintf('<comment>Line %d: Expected output does not match actual output</comment>', $output['line']));
                $io->write('<comment>Expected:</comment>');
                $io->write($expectedOutput);
                $io->write('<comment>Actual:</comment>');
                $io->write($actualOutput);

                throw new RuntimeException('README.md code snippet validation failed');
            }
        } finally {
            // Clean up temporary files
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            if (file_exists($tempOutput)) {
                unlink($tempOutput);
            }
        }
    }

    /**
     * Prepare code for execution by adjusting paths and includes
     */
    private function prepareExecutableCode(string $code, string $repositoryRoot) : string
    {
        // Replace relative vendor autoload path with absolute path
        $code = preg_replace(
            "/(include|require|include_once|require_once)\s+['\"]vendor\/autoload\.php['\"]/",
            "$1 '" . $repositoryRoot . "/vendor/autoload.php'",
            $code,
        );

        // If the code doesn't have an opening PHP tag, add it
        if ( ! str_starts_with(trim($code), '<?php')) {
            $code = "<?php\n" . $code;
        }

        return $code;
    }

    /**
     * Normalize output for comparison
     */
    private function normalizeOutput(string $output) : string
    {
        // Remove trailing whitespace from each line
        $lines = explode("\n", $output);
        $lines = array_map('rtrim', $lines);

        // Remove leading/trailing empty lines but preserve internal structure
        while ( ! empty($lines) && trim($lines[0]) === '') {
            array_shift($lines);
        }

        while ( ! empty($lines) && trim($lines[count($lines) - 1]) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }
}
