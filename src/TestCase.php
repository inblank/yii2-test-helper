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
        $fixtureDir = __DIR__ . DIRECTORY_SEPARATOR . '_fixtures' . DIRECTORY_SEPARATOR;
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
                foreach ($fixture as $tableName => $data) {
                    reset($data);
                    $defaultRow = array_fill_keys(array_keys(current($data)), null);
                    $defaultRowLength = count($defaultRow);
                    $data = array_map(static function ($row) use ($defaultRow, $defaultRowLength) {
                        return array_slice(array_merge($defaultRow, $row), 0, $defaultRowLength);
                    }, $data);
                    try {
                        Yii::$app->getDb()->createCommand()->batchInsert($tableName, array_keys(current($data)), $data)->execute();
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
