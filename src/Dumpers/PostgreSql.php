<?php

declare(strict_types=1);

namespace Snawbar\Backup\Dumpers;

use Spatie\DbDumper\Databases\PostgreSql as SpatiePostgreSql;

class PostgreSql extends SpatiePostgreSql
{
    private const ROWS_PER_INSERT = 500;

    private const TRAILER_BYTES = 8192;

    private const COMMENT = '--';

    private const HEADER_END_PREFIX = '-- Name: ';

    private const RESTRICT_PREFIX = '\restrict ';

    private const UNRESTRICT_FORMAT = '\unrestrict %s';

    private const VERSION_SPECIFIC_SETTINGS = [
        'SET transaction_timeout = 0;',
        'SET default_table_access_method = heap;',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->addExtraOption(sprintf('--rows-per-insert=%d', self::ROWS_PER_INSERT))
            ->addExtraOption('--no-owner')
            ->addExtraOption('--no-privileges');
    }

    public function dumpToFile(string $dumpFile): void
    {
        parent::dumpToFile($dumpFile);

        $this->makePortable(dumpFile: $dumpFile);
    }

    private function makePortable(string $dumpFile): void
    {
        $handle = fopen($dumpFile, 'r+b');

        $restrictKey = $this->commentOutHeader(handle: $handle);

        when(filled($restrictKey), fn () => $this->commentOutTrailer(handle: $handle, restrictKey: $restrictKey));

        fclose($handle);
    }

    private function commentOutHeader(mixed $handle): ?string
    {
        $restrictKey = NULL;
        $position = 0;

        while (($line = fgets($handle)) !== FALSE && ! str_starts_with($line, self::HEADER_END_PREFIX)) {
            $statement = rtrim($line, "\r\n");

            when($this->breaksPortability(statement: $statement), fn () => $this->commentOutAt(handle: $handle, position: $position));

            $restrictKey ??= $this->restrictKey(statement: $statement);
            $position = ftell($handle);
        }

        return $restrictKey;
    }

    private function commentOutTrailer(mixed $handle, string $restrictKey): void
    {
        $size = fstat($handle)['size'];
        $offset = max(0, $size - self::TRAILER_BYTES);

        fseek($handle, $offset);

        $index = strrpos((string) stream_get_contents($handle), sprintf(self::UNRESTRICT_FORMAT, $restrictKey));

        when($index !== FALSE, fn () => $this->commentOutAt(handle: $handle, position: $offset + $index));
    }

    private function commentOutAt(mixed $handle, int $position): void
    {
        $current = ftell($handle);

        fseek($handle, $position);
        fwrite($handle, self::COMMENT);
        fseek($handle, $current);
    }

    private function breaksPortability(string $statement): bool
    {
        return str_starts_with($statement, self::RESTRICT_PREFIX) || in_array($statement, self::VERSION_SPECIFIC_SETTINGS, TRUE);
    }

    private function restrictKey(string $statement): ?string
    {
        return match (str_starts_with($statement, self::RESTRICT_PREFIX)) {
            TRUE => substr($statement, strlen(self::RESTRICT_PREFIX)),
            FALSE => NULL,
        };
    }
}
