<?php

namespace Gurucomkz\DataObjectLogger;

use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\Queries\SQLUpdate;

/**
 *
 * Finds all non-compressed  bodies and compresses them
 *
 * Requires the gzip extension
 *
 * @author gurucomkz
 */
class CompressLogsTask extends BuildTask
{
    private static $segment = 'CompressLogsTask';

    protected $title = "Compress Log Entries Details";

    protected $total = 0;
    protected $table;
    protected $from;
    protected $where;
    protected $bytesInput = 0;
    protected $bytesOutput = 0;

    public function isEnabled()
    {
        return Director::is_cli() && parent::isEnabled() && function_exists('gzdeflate') && Config::forClass(ActivityLogEntry::class)->get('compress_body');
    }

    public function __construct()
    {
        $this->table = ActivityLogEntry::singleton()->baseTable();
        $this->from = "FROM \"{$this->table}\"";
        $this->where = "\"Details\" NOT LIKE '" . ActivityLogEntry::COMPRESSED_SIGNATURE . "%'";
    }


    public function findFirstId()
    {
        $query = "SELECT ID {$this->from} WHERE {$this->where} ORDER BY ID ASC LIMIT 1";
        $firstId = DB::query($query)->value();
        return $firstId ?: 0;
    }

    public function findLastId()
    {
        $query = "SELECT ID {$this->from} WHERE {$this->where} ORDER BY ID DESC LIMIT 1";
        $firstId = DB::query($query)->value();
        return $firstId ?: 0;
    }

    public function run($request)
    {
        $batchSize = 500;
        $lastId = 0;

        // Use first and last IDs to estimate total, avoiding full table scan
        $firstId = $this->findFirstId();
        $lastRowId = $this->findLastId();
        $this->total = ($lastRowId && $firstId) ? ($lastRowId - $firstId + 1) : 0;

        if (!$firstId || !$lastRowId) {
            echo "No non-compressed records found\n";
            return;
        }

        echo "Estimated " . $this->total . " non-compressed records (from ID $firstId to $lastRowId)\n";
        $processed = 0;
        while (true) {
            $query = "SELECT ID, \"Details\" {$this->from} WHERE ID > $lastId AND ID <= $lastRowId AND {$this->where} ORDER BY ID ASC LIMIT $batchSize";
            $nonCompressed = DB::query($query);
            $rows = iterator_to_array($nonCompressed);
            if (empty($rows)) {
                break;
            }
            foreach ($rows as $row) {
                $this->optimizeRow($row);
                $processed++;
                $lastId = $row['ID'];
            }
            $this->progress($processed);
            // Free memory
            unset($rows);
            gc_collect_cycles();
        }
        $this->progress($processed);
        // optimise table
        $this->optimizeTable();
        echo "\n";
    }

    public function optimizeRow(array $row)
    {
        if (!$row['Details']) {
            echo "Record with ID {$row['ID']} has no body\n";
            return;
        }
        $shortened = json_decode(json_encode($row['Details'] ?? '')); // remove whitespace
        $compressed = gzdeflate($shortened ?? '');
        if ($compressed === false) {
            echo "\tFailed to compress email with ID {$row['ID']}\n";
            return;
        }
        $this->bytesInput += strlen($row['Details']);

        $base64compressed = ActivityLogEntry::COMPRESSED_SIGNATURE . base64_encode($compressed);

        $this->bytesOutput += strlen($base64compressed);

        SQLUpdate::create($this->table, [
            'Details' => $base64compressed,
        ], [
            'ID' => $row['ID'],
        ])->execute();
    }

    public function optimizeTable()
    {
        $table = ActivityLogEntry::singleton()->baseTable();
        echo "\nOptimizing $table table...\n";
        $db = DB::get_conn();
        if ($db instanceof MySQLDatabase) {
            DB::query("OPTIMIZE TABLE \"$table\"");
        } elseif (get_class($db) == "SilverStripe\PostgreSQL\PostgreSQLDatabase") {
            DB::query("VACUUM FULL \"$table\"");
        } else {
            echo "Database not supported for optimization\n";
        }
    }

    const PROGRRESS_SPINNER = [
        '⠋',
        '⠙',
        '⠹',
        '⠼',
        '⠴',
        '⠦',
        '⠧',
        '⠇',
    ];

    private $edgePos = 0;
    public function progress($pos)
    {
        if ($this->total) {
            $this->edgePos++;
            $percent = round($pos / $this->total * 100, 2);
            $percentInt = floor($percent);

            $compressionRatio = $this->bytesInput ? round((1 - $this->bytesOutput / $this->bytesInput) * 100, 2) : 0;
            $compressionGainSz = round(($this->bytesInput - $this->bytesOutput) / 1024 / 1024);

            $size = 30;
            $dotsz = $percentInt / (100 / $size);
            $readyStr = str_repeat('￭', (int)floor($dotsz));
            $blankStr = str_repeat(' ', max(0, $size - mb_strlen($readyStr) - 1));
            $edge = self::PROGRRESS_SPINNER[$this->edgePos % count(self::PROGRRESS_SPINNER)];

            $spinner = "\rConverting: [";
            $spinner .= $readyStr;
            $spinner .= "$edge";
            $spinner .= $blankStr;
            $spinner .= "] $percent%   ";
            $spinner .= ". Gain: $compressionRatio% ($compressionGainSz mb)  ";
            echo $spinner;
            if ($percentInt > 0 && $percentInt % 100 == 0) {
                echo "\n";
            }
        }
    }
}
