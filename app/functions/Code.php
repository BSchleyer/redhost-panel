<?php


$code = new Code();
class Code extends Controller
{

    public function getCode($code)
    {
        $SQL = self::db()->prepare("SELECT * FROM `codes` WHERE `code` = :code");
        $SQL->execute(array(":code" => $code));
        if ($SQL->rowCount() == 1) {
            return $SQL->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    public function getUsed($code, $user_id)
    {
        $SQL = self::db()->prepare("SELECT * FROM `code_used` WHERE `code` = :code AND `user_id` = :user_id");
        $SQL->execute(array(":code" => $code, ":user_id" => $user_id));

        return $SQL->rowCount();
    }

    public function codeUsage($code)
    {
        $code = self::getCode($code);

        if ($code['useable'] != 0) {
            return true;
        }

        return false;
    }

    public function useCode($code, $user_id)
    {
        $data = self::getCode($code);

        $code_amount = $data['amount'];

        if ($this->codeUsage($code) != 0) {
            $SQL = self::db()->prepare("INSERT INTO `code_used` (`code`, `user_id`) VALUES (:code, :user_id)");
            $SQL->execute(array(":code" => $code, ":user_id" => $user_id));

            $SQL1 = self::db()->prepare("UPDATE `codes` SET `useable` = :useable WHERE `code` = :code");
            $SQL1->execute(array(":useable" => $data['useable'] - 1, ":code" => $code));

            return true;
        }

        return false;
    }

}