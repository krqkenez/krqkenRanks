<?php

declare(strict_types=1);

namespace krqkenez\ranks\object;

class Rank
{
	private string $coloredName;

	public function __construct(
		private string $name,
		private int $priority,
		private string $color,
		private string $displayName,
		private string $chatFormat,
		private string $nameTag,
		private array $permissions
	) {
		$this->coloredName = $this->color . $this->displayName;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getPriority(): int
	{
		return $this->priority;
	}

	public function getColor(): string
	{
		return $this->color;
	}

	public function getDisplayName(): string
	{
		return $this->displayName;
	}

	public function getColoredName(): string
	{
		return $this->coloredName;
	}

	public function getChatFormat(): string
	{
		return $this->chatFormat;
	}

	public function getNameTag(): string
	{
		return $this->nameTag;
	}

	public function getPermissions(): array
	{
		return $this->permissions;
	}
}