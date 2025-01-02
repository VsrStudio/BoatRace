# BoatRace

BoatRace is a fun and competitive racing plugin for Minecraft Pocket Edition, where players can race in custom arenas using boats. The plugin supports multiple arenas, rewards for winners, and a scoreboard to track player progress.

## Features

- **Multiple Arenas**: Set up and configure different race arenas.
- **Race Start Mechanism**: Players can join and start races.
- **Reward System**: Winners receive a monetary reward.
- **Scoreboard Integration**: Displays race status and player progress.
- **Customizable Config**: Easily configure arenas and reward money.
# Dependency
- EconomyAPI
- Scorehud
- FormAPI

## Commands

- `/joinrace <arena>`: Join the specified race arena.
- `/startrace <arena>`: Start the race in the specified arena (requires admin permission).
- `/racestats`: View your race win statistics.
- `/raceui`: Open the UI to select a race arena.

## Permissions

- `boatrace.join`: Permission to join races (default: true).
- `boatrace.start`: Permission to start races (default: op).
- `boatrace.stats`: Permission to view race statistics (default: true).
- `boatrace.ui`: Permission to open the race selection UI (default: true).

## Configuration

### `scorehud`
```
Scorehud:
  Arena: "{boatrace.arena}"
  Wins: "{boatrace.wins}"
```

### `config.yml`

The `config.yml` file allows you to configure the race arenas and reward system. Here’s an example of the configuration:

```yaml
arenas:
  arena1:
    world: world
    start:
      x: 100
      y: 64
      z: 100
    finish:
      x: 150
      y: 64
      z: 150
    max_players: 4
  arena2:
    world: world
    start:
      x: 200
      y: 64
      z: 200
    finish:
      x: 250
      y: 64
      z: 250
    max_players: 4

reward_money: 500  # Reward money for the winner
