<?php

namespace BoatRace;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\block\VanillaBlocks;
use jojoe77777\FormAPI\SimpleForm;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\ScoreHud;

class Main extends PluginBase implements Listener {

    private array $arenas = [];
    private array $playersInArena = [];
    private array $raceWinners = [];
    private Config $statsConfig;
    private ?\onebone\economyapi\EconomyAPI $economyAPI = null;

    public function onEnable(): void {
        $this->economyAPI = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        if ($this->economyAPI === null) {
            $this->getLogger()->warning("EconomyAPI tidak ditemukan. Hadiah uang dinonaktifkan.");
        }

        $this->saveDefaultConfig();
        $this->statsConfig = new Config($this->getDataFolder() . "stats.yml", Config::YAML);
        $this->loadArenas();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info(TextFormat::GREEN . "BoatRace Plugin Enabled!");
    }

    private function loadArenas(): void {
        $config = $this->getConfig();
        foreach ($config->get("arenas", []) as $arenaName => $data) {
            $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);
            if ($world !== null) {
                $this->arenas[$arenaName] = [
                    "startPosition" => new Position($data["start"]["x"], $data["start"]["y"], $data["start"]["z"], $world),
                    "finishPosition" => new Position($data["finish"]["x"], $data["finish"]["y"], $data["finish"]["z"], $world),
                    "maxPlayers" => $data["max_players"] ?? 4 
                ];
            } else {
                $this->getLogger()->warning("World {$data['world']} for arena {$arenaName} not found!");
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Command ini hanya bisa dijalankan oleh pemain.");
            return false;
        }

        switch ($command->getName()) {
            case "joinrace":
                if (isset($args[0])) {
                    $this->joinRace($sender, $args[0]);
                } else {
                    $sender->sendMessage(TextFormat::RED . "Gunakan: /joinrace <arena>");
                }
                return true;

            case "startrace":
                if (isset($args[0])) {
                    $this->startRaceForArena($args[0]);
                } else {
                    $sender->sendMessage(TextFormat::RED . "Gunakan: /startrace <arena>");
                }
                return true;

            case "racestats":
                $wins = $this->statsConfig->get($sender->getName(), 0);
                $sender->sendMessage(TextFormat::AQUA . "Anda telah memenangkan $wins perlombaan.");
                return true;

            case "raceui":
                $this->showRaceUI($sender);
                return true;
        }

        return false;
    }

    private function joinRace(Player $player, string $arena): void {
        if (!isset($this->arenas[$arena])) {
            $player->sendMessage(TextFormat::RED . "Arena $arena tidak ditemukan.");
            return;
        }

        if (count($this->playersInArena[$arena] ?? []) >= $this->arenas[$arena]["maxPlayers"]) {
            $player->sendMessage(TextFormat::RED . "Arena $arena penuh.");
            return;
        }

        $this->playersInArena[$arena][$player->getName()] = ["startTime" => 0];
        $player->teleport($this->arenas[$arena]["startPosition"]);
        $player->sendMessage(TextFormat::GREEN . "Anda telah bergabung dalam perlombaan di arena $arena!");

        $this->updateScoreHud($player, $arena);

        // Otomatis mulai permainan jika jumlah pemain mencukupi
        if (count($this->playersInArena[$arena]) >= $this->arenas[$arena]["maxPlayers"]) {
            $this->startRaceForArena($arena);
        }
    }

    private function startRaceForArena(string $arena): void {
        if (!isset($this->playersInArena[$arena]) || empty($this->playersInArena[$arena])) {
            $this->getServer()->broadcastMessage(TextFormat::RED . "Tidak ada pemain di arena $arena. Perlombaan dibatalkan.");
            return;
        }

        $this->getServer()->broadcastMessage(TextFormat::YELLOW . "Perlombaan di arena $arena dimulai!");
        foreach ($this->playersInArena[$arena] as $playerName => $playerData) {
            $player = $this->getServer()->getPlayerExact($playerName);
            if ($player !== null) {
                $this->playersInArena[$arena][$playerName]["startTime"] = microtime(true);
                $player->sendMessage(TextFormat::GREEN . "Perlombaan dimulai!");
            }
        }
    }

    private function finishRace(Player $player, string $arena): void {
        unset($this->playersInArena[$arena][$player->getName()]);
        $wins = $this->statsConfig->get($player->getName(), 0);
        $this->statsConfig->set($player->getName(), $wins + 1);
        $this->statsConfig->save();

        if ($this->economyAPI !== null) {
            $this->economyAPI->addMoney($player, $this->getConfig()->get("reward_money", 500));
            $player->sendMessage(TextFormat::GOLD . "Anda mendapatkan hadiah uang sebesar {$this->getConfig()->get("reward_money", 500)} untuk memenangkan perlombaan!");
        }

        $this->updateScoreHud($player, null);
        $player->sendMessage(TextFormat::GOLD . "Selamat, Anda memenangkan perlombaan di arena $arena!");
    }

    private function updateScoreHud(Player $player, ?string $arena): void {
        $arenaName = $arena ?? "Tidak ada";
        $wins = $this->statsConfig->get($player->getName(), 0);

        (new PlayerTagUpdateEvent(
            $player,
            "boatrace.arena",
            $arenaName
        ))->call();

        (new PlayerTagUpdateEvent(
            $player,
            "boatrace.wins",
            (string)$wins
        ))->call();
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        foreach ($this->playersInArena as $arena => $players) {
            if (isset($players[$player->getName()])) {
                $currentPosition = $player->getPosition();
                if ($currentPosition->distance($this->arenas[$arena]["finishPosition"]) < 2) {
                    $this->finishRace($player, $arena);
                }
                return;
            }
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $this->updateScoreHud($player, null);
    }

    private function showRaceUI(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data !== null) {
                $arenas = array_keys($this->arenas);
                if (isset($arenas[$data])) {
                    $this->joinRace($player, $arenas[$data]);
                } else {
                    $player->sendMessage(TextFormat::RED . "Arena tidak valid.");
                }
            }
        });

        $form->setTitle("Pilih Arena");
        $form->setContent("Silakan pilih arena untuk bergabung:");
        foreach (array_keys($this->arenas) as $arena) {
            $form->addButton($arena);
        }

        $player->sendForm($form);
    }
}
