<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring\Collectors\Concerns;

trait ReadsFileTail
{
    /**
     * Lê as últimas N linhas de um arquivo, sem carregar o arquivo inteiro
     * (lê de trás pra frente em blocos). Retorna [] se não puder ler.
     *
     * @return array<int, string>
     */
    protected function tail(string $path, int $lines = 20): array
    {
        if ($lines <= 0 || ! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $stat = fstat($handle);
        $position = $stat['size'] ?? 0;
        $buffer = '';
        $chunkSize = 4096;
        $newlines = 0;

        while ($position > 0 && $newlines <= $lines) {
            $read = (int) min($chunkSize, $position);
            $position -= $read;

            fseek($handle, $position, SEEK_SET);
            $buffer = fread($handle, $read) . $buffer;
            $newlines = substr_count($buffer, "\n");
        }

        fclose($handle);

        $all = preg_split('/\r?\n/', rtrim($buffer, "\r\n"));

        return array_slice($all, -$lines);
    }
}
