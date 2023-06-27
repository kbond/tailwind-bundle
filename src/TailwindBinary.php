<?php

namespace Symfonycasts\TailwindBundle;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TailwindBinary
{
    private const VERSION = 'v3.3.2';
    private HttpClientInterface $httpClient;

    public function __construct(
        private string $binaryDownloadDir,
        private ?SymfonyStyle $output = null,
        HttpClientInterface $httpClient = null,
    )
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function createProcess(string $input, string $output, array $arguments = []): Process
    {
        $binary = $this->binaryDownloadDir . '/' . self::getBinaryName();
        if (!is_file($binary)) {
            $this->downloadExecutable();
        }

        return new Process(
            array_merge([$binary, '-i', $input, '-o', $output], $arguments),
        );
    }

    private function downloadExecutable(): void
    {
        $url = sprintf('https://github.com/tailwindlabs/tailwindcss/releases/download/%s/%s', self::VERSION, self::getBinaryName());

        $this->output?->note(sprintf('Downloading TailwindCSS binary from %s', $url));

        if (!is_dir($this->binaryDownloadDir)) {
            mkdir($this->binaryDownloadDir, 0777, true);
        }

        $targetPath = $this->binaryDownloadDir . '/' . self::getBinaryName();
        $progressBar = null;

        $response = $this->httpClient->request('GET', $url, [
            'on_progress' => function (int $dlNow, int $dlSize, array $info) use (&$progressBar): void {
                // dlSize is not known at the start
                if ($dlSize === 0) {
                    return;
                }

                if (!$progressBar) {
                    $progressBar = $this->output?->createProgressBar($dlSize);
                }

                $progressBar->setProgress($dlNow);
            },
        ]);
        $fileHandler = fopen($targetPath, 'w');
        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);
        $progressBar?->finish();
        $this?->output->writeln('');
        // make file executable
        chmod($targetPath, 0777);
    }

    private static function getBinaryName(): string
    {
        $os = strtolower(PHP_OS);
        $machine = php_uname('m');

        if (str_contains($os, 'darwin')) {
            if ($machine === 'arm64') {
                return 'tailwindcss-macos-arm64';
            }
            if ($machine === 'x86_64') {
                return 'tailwindcss-macos-x64';
            }

            throw new \Exception(sprintf('No matching machine found for Darwin platform (Machine: %s).', $machine));
        }

        if (str_contains($os, 'linux')) {
            if ($machine === 'arm64') {
                return 'tailwindcss-linux-arm64';
            }
            if ($machine === 'armv7') {
                return 'tailwindcss-linux-armv7';
            }
            if ($machine === 'x86_64') {
                return 'tailwindcss-linux-x64';
            }

            throw new \Exception(sprintf('No matching machine found for Linux platform (Machine: %s).', $machine));
        }

        if (str_contains($os, 'win')) {
            if ($machine === 'arm64') {
                return 'tailwindcss-windows-arm64.exe';
            }
            if ($machine === 'x86_64') {
                return 'tailwindcss-windows-x64.exe';
            }

            throw new \Exception(sprintf('No matching machine found for Windows platform (Machine: %s).', $machine));
        }

        throw new \Exception(sprintf('Unknown platform or architecture (OS: %s, Machine: %s).', $os, $machine));
    }
}
