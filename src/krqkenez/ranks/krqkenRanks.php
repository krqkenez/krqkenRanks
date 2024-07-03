<?php


declare(strict_types=1);

namespace krqkenez\ranks;

use krqkenez\ranks\command\RankCommand;
use krqkenez\ranks\event\PlayerRankChangeEvent;
use krqkenez\ranks\exception\RanksException;
use krqkenez\ranks\listener\chat\ChatListener;
use krqkenez\ranks\listener\RankListener;
use krqkenez\ranks\object\Rank;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\PermissionManager;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

use LevelDB;

final class krqkenRanks extends PluginBase
{
	private static self $instance;

	private LevelDB $database;

	private array $ranks;
	private array $rankList;
	private array $rankInheritances;
	private array $permissions;
	private array $aliasesToRanks;
	private array $messages;

	private string $defaultRank;

	private bool $localChatEnabled;
	private array $localChatSettings;

	/** @var array<string, PermissionAttachment> */
	private array $attachments = [];
	/** @var array<string, Rank> */
	private array $playerRanks = [];

	private function initializeDatabase(): void
	{
		if (!(is_dir($this->getDataFolder() . "data"))) {
			@mkdir($this->getDataFolder() . "data");
		}
		$this->database = new LevelDB($this->getDataFolder() . "data/ranks");
	}

	private function readInheritances(): void
	{
		foreach ($this->ranks as $rankName => $rankData) {
			if ($rankData["inheritance"] !== []) {
				foreach ($rankData["inheritance"] as $inheritance) {
					$this->rankInheritances[$rankName][] = $inheritance;
				}
			} else {
				$this->rankInheritances[$rankName] = [];
			}
		}
	}

	private function readPermissions(): void
	{
		foreach ($this->ranks as $rankName => $rankData) {
			$permissions = $rankData["permissions"];
			foreach ($this->rankInheritances[$rankName] as $childRank) {
				$permissions = array_merge($permissions, $this->ranks[$childRank]["permissions"]);
			}
			$this->permissions[$rankName] = $permissions;
		}
	}

	private function readAliases(): void
	{
		foreach ($this->ranks as $rankName => $rankData) {
			if ($rankData["alias"] === "") {
				continue;
			}
			$this->aliasesToRanks[$rankData["alias"]] = $rankName;
		}
	}

	private function initializeConfigData(): void
	{
		$configData = $this->getConfig()->getAll();
		$this->ranks = $configData["ranks"];
		$this->rankList = array_keys($configData["ranks"]);

		$this->readInheritances();
		$this->readPermissions();
		$this->readAliases();

		$this->messages = $configData["messages"];

		$defaultRank = strtolower($configData["settings"]["default-rank"]);
		if (!(in_array($defaultRank, $this->rankList))) {
			throw new RanksException(sprintf("Default rank '%s' does not exist", $defaultRank));
		}

		$this->defaultRank = $defaultRank;
	}

	private function registerCommands(): void
	{
        $this->getServer()->getCommandMap()->registerAll("krqkenranks", [
            new RankCommand($this),
        ]);
	}

	private function registerListeners(): void
	{
		$pluginManager = $this->getServer()->getPluginManager();
		$pluginManager->registerEvents(new RankListener($this), $this);
		$pluginManager->registerEvents(new ChatListener($this), $this);
	}

	public function onEnable(): void
	{
		self::$instance = $this;

		$this->saveDefaultConfig();

		$this->initializeDatabase();
		$this->initializeConfigData();

		$this->registerCommands();
		$this->registerListeners();
	}

	public static function getInstance(): self
	{
		return self::$instance;
	}

	public function getRankList(): array
	{
		return $this->rankList;
	}

	public function getMessages(): array
	{
		return $this->messages;
	}

	public function getDefaultRank(): string
	{
		return $this->defaultRank;
	}

	public function doesRankExist(string $rank): bool
	{
		return in_array(strtolower($rank), $this->rankList, true);
	}

	public function isDefaultRank(string $rank): bool
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}
		return strtolower($rank) === $this->defaultRank;
	}

	public function isLocalChatEnabled(): bool
	{
		return $this->localChatEnabled;
	}

	public function getLocalChatSettings(): array
	{
		return $this->localChatSettings;
	}

	public function getRankPriority(string $rank): int
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}
		return (int)$this->ranks[strtolower($rank)]["priority"];
	}

	public function getRankColor(string $rank): string
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}
		return $this->ranks[strtolower($rank)]["color"];
	}

	public function getRankDisplayName(string $rank): string
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}
		return $this->ranks[strtolower($rank)]["display-name"];
	}

	public function getRankChatFormat(string $rank): string
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}
		return $this->ranks[strtolower($rank)]["chat-format"];
	}

	public function getRankNameTag(string $rank): string
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}
		return $this->ranks[strtolower($rank)]["name-tag"];
	}

	public function getRankPermissions(string $rank): array
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}
		return $this->permissions[strtolower($rank)];
	}

	public function getRankByName(string $rank): Rank
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}
		$rankName = strtolower($rank);
		$rankData = $this->ranks[$rankName];
		return new Rank(
			$rankName,
			$rankData["priority"],
			$rankData["color"],
			$rankData["display-name"],
			$rankData["chat-format"],
			$rankData["name-tag"],
			$this->getRankPermissions($rankName)
		);
	}

	public function getRankFromAlias(string $alias): string
	{
		return $this->aliasesToRanks[strtolower($alias)] ?? $alias;
	}

	public function getRank(Player $player): Rank
	{
		$nickname = strtolower($player->getName());
		$rank = $this->getRankFromDatabase($nickname);
		$rankData = $this->ranks[$rank];

		return $this->playerRanks[$nickname] ?? new Rank(
			$rank,
			$rankData["priority"],
			$rankData["color"],
			$rankData["display-name"],
			$rankData["chat-format"],
			$rankData["name-tag"],
			$this->getRankPermissions($rank)
		);
	}

	public function getRankFromDatabase(string $nickname): string
	{
		$rank = $this->database->get(strtolower($nickname));
		return $rank !== false ? $rank : $this->defaultRank;
	}

	public function setRank(Player $player, string $rank): void
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}

		$rank = strtolower($rank);
		$nickname = strtolower($player->getName());
		$oldRank = $this->getRank($player);

		if ($this->database->get($nickname) !== false) {
			$this->database->delete($nickname);
		}

		if (!($this->isDefaultRank($rank))) {
			$this->database->put($nickname, $rank);
		}

		$this->addRank($player, $rank);
		(new PlayerRankChangeEvent($player, $oldRank))->call();
	}

	public function setRankOffline(string $nickname, string $rank): void
	{
		if (!($this->doesRankExist($rank))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rank));
		}

		$rank = strtolower($rank);
		$nickname = strtolower($nickname);

		if ($this->database->get($nickname) !== false) {
			$this->database->delete($nickname);
		}

		if (!($this->isDefaultRank($rank))) {
			$this->database->put($nickname, $rank);
		}
	}

	/** @internal */
	public function addRank(Player $player, string $rankName): void
	{
		$rankName = strtolower($rankName);
		if (!($this->doesRankExist($rankName))) {
			throw new RanksException(sprintf("Rank '%s' does not exist", $rankName));
		}

		$this->removeRank($player);
		$rankData = $this->ranks[$rankName];
		$this->playerRanks[strtolower($player->getName())] = new Rank(
			$rankName,
			$rankData["priority"],
			$rankData["color"],
			$rankData["display-name"],
			$rankData["chat-format"],
			$rankData["name-tag"],
			$this->getRankPermissions($rankName)
		);
	}

	/** @internal */
	public function removeRank(Player $player): void
	{
		$nickname = strtolower($player->getName());
		if (isset($this->playerRanks[$nickname])) {
			unset($this->playerRanks[$nickname]);
		}
	}

	/** @internal */
	public function getAttachment(Player $player): ?PermissionAttachment
	{
		$nickname = strtolower($player->getName());
		return !(isset($this->attachments[$nickname])) ? null : $this->attachments[$nickname];
	}

	/** @internal */
	public function setAttachment(Player $player, PermissionAttachment $attachment): void
	{
		$this->attachments[strtolower($player->getName())] = $attachment;
	}

	/** @internal */
	public function removeAttachment(Player $player): void
	{
		$nickname = strtolower($player->getName());
		if (isset($this->attachments[$nickname])) {
			unset($this->attachments[$nickname]);
		}
	}

	public function updatePermissions(Player $player): void
	{
		$attachment = $this->getAttachment($player);
		if ($attachment === null) {
			return;
		}
		$attachment->clearPermissions();
		foreach ($this->getRank($player)->getPermissions() as $permission) {
			if ($permission === "*") {
				foreach (PermissionManager::getInstance()->getPermissions() as $tempPermission) {
					$attachment->setPermission($tempPermission->getName(), true);
				}
				break;
			}
			$attachment->setPermission($permission, true);
		}
	}

	/** @internal */
	public function notifyRankChange(string $issuer, string $target, string $rank): void
	{
		foreach ($this->getServer()->getOnlinePlayers() as $player) {
			if ($player->hasPermission("krqkenranks.notifications")) {
				$player->sendMessage(str_replace(["{ISSUER}", "{TARGET}", "{RANK}"], [$issuer, $target, $rank], $this->messages["player"]["rank-change-notification"]));
			}
		}
		$this->getLogger()->info(str_replace(["{ISSUER}", "{TARGET}", "{RANK}"], [$issuer, $target, $rank], $this->messages["console"]["rank-change-notification"]));
	}


	public function updateNameTag(Player $player): void
	{
		$player->setNameTag(str_replace(
			["{NAME}", "{DISPLAY_NAME}", "{LINE}"],
			[$player->getName(), $player->getDisplayName(), "\n"],
			$this->getRank($player)->getNameTag()
		));
	}
}