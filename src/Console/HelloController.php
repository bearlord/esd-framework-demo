<?php


namespace App\Console;


use ESD\Yii\Console\Controller;
use ESD\Yii\Console\ExitCode;
use ESD\Yii\Db\Connection;
use ESD\Yii\Db\Query;
use ESD\Yii\Helpers\Console;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class HelloController extends Controller
{
    /**
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     * @return int Exit code
     */
    public function actionIndex($message = 'hello world')
    {
        echo $message . "\n";
        $this->runQuery();
        $contactsId = $this->prompt('输入要改名字的contacts_id', ['required' => true, 'validator' => function ($input, &$error) {
            if ($input <= 0) {
                $error = '输入错误!';
                return false;
            }
            return true;
        }]);
        $name = $this->prompt('输入要新的名字', ['required' => true, 'validator' => function ($input, &$error) {
            if (strlen($input) <= 1) {
                $error = '输入错误!';
                return false;
            }
            return true;
        }]);
        if ($contactsId && $name) {
            (new Connection())
                ->createCommand("UPDATE n_crm_contacts SET name = :name WHERE contacts_id = :contacts_id", [
                    ":contacts_id" => $contactsId,
                    ":name" => $name
                ])->query();
        }
        $this->runQuery();
        return ExitCode::OK;
    }

    protected function runQuery()
    {
        $row = (new Query())
            ->from('n_crm_contacts')
            ->limit(3)
            ->all();
        if (!empty($row)) {
            foreach ($row as $key => $value) {
                printf("contacts_id: %s\t, name: %s\n", $value['contacts_id'], $value['name']);
            }
        }
    }

    public function actionPick()
    {
        printf("开始采集...\n");
        sleep(20);
    }
}