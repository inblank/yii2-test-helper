<?php

namespace inblank\testhelper;

use PHPUnit\Framework\MockObject\RuntimeException;
use Yii;
use yii\db\Exception;
use yii\db\Transaction;
use yii\helpers\Json;

/**
 * General class for tests
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Path to fixture files
     * Default: Yii::$app->basePath . '/_fixtures
     * @var string
     */
    public $fixturePath;

    /**
     * Transaction running before test execution
     * @var Transaction
     */
    protected $runningTransaction;

    /**
     * List of loaded fixtures
     * @var array
     */
    protected $loadedFixtures;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        $this->runningTransaction = Yii::$app->getDb()->beginTransaction();
        parent::setUp();
        // load default fixtures
        $this->loadedFixtures = [];
        $this->loadFixtures('_default');
    }

    /**
     * Loading fixtures
     * @param array|string $fixtures filename or list of fixture filenames to load
     */
    public function loadFixtures($fixtures): void
    {
        if (empty($this->fixturePath)) {
            $this->fixturePath = Yii::$app->basePath . DIRECTORY_SEPARATOR . '_fixtures' . DIRECTORY_SEPARATOR;
        }
        $fixtureDir = rtrim($this->fixturePath, '/\\') . DIRECTORY_SEPARATOR;
        // load only unloaded fixtures
        foreach ((array)$fixtures as $name) {
            if (empty($name)) {
                // нечего загружать
                continue;
            }
            if (!array_key_exists($name, $this->loadedFixtures)) {
                $this->loadedFixtures[$name] = true;
                foreach (['php', 'json'] as $extension) {
                    $fileName = "{$fixtureDir}{$name}.{$extension}";
                    if (file_exists($fileName)) {
                        break;
                    }
                    $fileName = false;
                }
                if (empty($fileName)) {
                    continue;
                }
                $fixture = $extension === 'php' ? include $fileName : Json::decode(file_get_contents($fileName));
                $db = Yii::$app->getDb();
                foreach ($fixture as $tableName => $data) {
                    if (empty($data) || !is_array($data)) {
                        // empty or incorrect data
                        continue;
                    }
                    reset($data);
                    $defaultRow = array_fill_keys(array_keys(current($data)), null);
                    $defaultRowLength = count($defaultRow);
                    $data = array_map(static function ($row) use ($defaultRow, $defaultRowLength) {
                        return array_slice(array_merge($defaultRow, $row), 0, $defaultRowLength);
                    }, $data);
                    try {
                        $currentRow = current($data);
                        $db->createCommand()->batchInsert($tableName, array_keys($currentRow), $data)->execute();
                        // fix sequence for Postgres if table contains primary key 'id'
                        if (($db->driverName === 'pgsql') && array_key_exists('id', $currentRow)) {
                            $db->createCommand($db->queryBuilder->resetSequence($tableName))->execute();
                        }
                    } catch (Exception $e) {
                        throw new RuntimeException('Fixture error: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->runningTransaction->rollBack();
    }
}
