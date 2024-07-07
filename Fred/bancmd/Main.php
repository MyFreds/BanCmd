<?php

namespace Fred\bancmd;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\player\Player;
use pocketmine\command\{Command, CommandSender};
use pocketmine\event\server\CommandEvent;

final class Main extends PluginBase implements Listener 
{
    private Config $cfg;
    private array $bannedCommands = [];
    
    public function onEnable(): void{
        @mkdir($this->getDataFolder());
        
        $this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML, ["commands" => []]);
        
        $this->initCommands();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    private function initCommands(): void {
        foreach ($this->cfg->get("commands", []) as $cmd => $data) {
            $this->bannedCommands[strtolower($cmd)] = $data;
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "bancmd") {
            if (count($args) < 1) {
                $sender->sendMessage("§7[§cBanCmd§7] §fUsage: /bancmd <add|remove|world|list> <command> [<worldName>]");
                return false;
            }

            $action = strtolower($args[0]);

            switch ($action) {
                case "add":
                    if (count($args) < 2) {
                        $sender->sendMessage("§7[§cBanCmd§7] §fUsage: /bancmd add <command>");
                        return false;
                    }
                    $cmd = strtolower($args[1]);
                    $this->cfg->setNested("commands.global.$cmd", true);
                    $this->cfg->save();
                    $this->initCommands();
                    $sender->sendMessage("§7[§cBanCmd§7] §aGlobal command §f$cmd §ahas been banned.");
                    break;

                case "remove":
                    if (count($args) < 2) {
                        $sender->sendMessage("§7[§cBanCmd§7] §fUsage: /bancmd remove <command>");
                        return false;
                    }
                    $cmd = strtolower($args[1]);
                    $this->cfg->removeNested("commands.global.$cmd");
                    $this->cfg->save();
                    $this->initCommands();
                    $sender->sendMessage("§7[§cBanCmd§7] §bGlobal command §f$cmd §bhas been unbanned.");
                    break;

                case "world":
                    if (count($args) < 4) {
                        $sender->sendMessage("§7[§cBanCmd§7] §fUsage: /bancmd world <add|remove> <command> <worldName>");
                        return false;
                    }
                    $worldAction = strtolower($args[1]);
                    $cmd = strtolower($args[2]);
                    $worldName = strtolower($args[3]);

                    $worldManager = $this->getServer()->getWorldManager();
                    if (!$worldManager->isWorldGenerated($worldName)) {
                        $sender->sendMessage("§7[§cBanCmd§7] §eWorld §f$worldName §enot found.");
                        return false;
                    }

                    if ($worldAction === "add") {
                        $this->cfg->setNested("commands.worlds.$worldName.$cmd", true);
                        $this->cfg->save();
                        $this->initCommands();
                        $sender->sendMessage("§7[§cBanCmd§7] §aCommand §f$cmd §ahas been banned in world §f" . $worldName . "§a.");
                    } elseif ($worldAction === "remove") {
                        $this->cfg->removeNested("commands.worlds.$worldName.$cmd");
                        $this->cfg->save();
                        $this->initCommands();
                        $sender->sendMessage("§7[§cBanCmd§7] §bCommand §f$cmd §bhas been unbanned in world §f$worldName.");
                    } else {
                        $sender->sendMessage("§7[§cBanCmd§7] §fUsage: /bancmd world <add|remove> <command> <worldName>");
                        return false;
                    }
                    break;

                case "list":
                    $globalCommands = $this->cfg->getNested("commands.global", []);
                    $worldCommands = $this->cfg->getNested("commands.worlds", []);
                    
                    $sender->sendMessage("§7List Global Banned Commands:");
                    foreach ($globalCommands as $cmd => $state) {
                        if ($state) {
                            $sender->sendMessage("§7- §e$cmd");
                        }
                    }

                    $sender->sendMessage("§7List World Banned Commands:");
                    foreach ($worldCommands as $worldName => $commands) {
                        foreach ($commands as $cmd => $state) {
                            if ($state) {
                                $sender->sendMessage("§7- §c$worldName: §e$cmd");
                            }
                        }
                    }
                    break;

                default:
                    $sender->sendMessage("§7[§cBanCmd§7] §fUsage: /bancmd <add|remove|world|list> <command> [<worldName>]");
                    return false;
            }

            return true;
        }

        return false;
    }

    public function onCommandEvent(CommandEvent $event): void {
        $sender = $event->getSender();
        if ($sender instanceof Player) {
            $message = strtolower($event->getCommand());
            $args = explode(" ", $message);
            $baseCmd = $args[0];

            if ($this->cfg->getNested("commands.global.$baseCmd", false)) {
                $sender->sendMessage("§7[§cBanCmd§7]§e Command §f$baseCmd §eis banned globally.");
                $event->cancel();
                return;
            }

            $worldName = strtolower($sender->getWorld()->getFolderName());
            if ($this->cfg->getNested("commands.worlds.$worldName.$baseCmd", false)) {
                $sender->sendMessage("§7[§cBanCmd§7] §eCommand §f$baseCmd §eis banned in this world.");
                $event->cancel();
                return;
            }
        }
    }
}
