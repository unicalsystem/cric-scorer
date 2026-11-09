-- Cric-Scorer by Aaryush - database schema (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS follows;
DROP TABLE IF EXISTS academy_progress;
DROP TABLE IF EXISTS interruptions;
DROP TABLE IF EXISTS balls;
DROP TABLE IF EXISTS innings_batters;
DROP TABLE IF EXISTS innings;
DROP TABLE IF EXISTS match_players;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS tournament_teams;
DROP TABLE IF EXISTS tournaments;
DROP TABLE IF EXISTS match_types;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS user_tokens;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NULL,
  google_sub VARCHAR(64) NULL,
  avatar_url VARCHAR(255) NULL,
  avatar_preset VARCHAR(20) NULL,
  bio VARCHAR(200) NULL,
  role VARCHAR(20) NULL,
  batting_style VARCHAR(30) NULL,
  bowling_style VARCHAR(40) NULL,
  experience_years TINYINT NULL,
  player_id INT NULL,
  terms_accepted_at DATETIME NULL,
  is_private TINYINT(1) NOT NULL DEFAULT 0,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_google_sub (google_sub),
  UNIQUE KEY uniq_player (player_id),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  INDEX (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  short_name VARCHAR(10) NOT NULL,
  color VARCHAR(10) NOT NULL DEFAULT '#0d4a36',
  category ENUM('ipl','international','custom') NOT NULL DEFAULT 'custom',
  owner_id INT NULL,
  share_mode ENUM('approved','everyone') NOT NULL DEFAULT 'approved',
  captain VARCHAR(100) NULL,
  home_ground VARCHAR(150) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (category),
  INDEX (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  role ENUM('Batter','Bowler','All-rounder','Wicket-keeper') NOT NULL DEFAULT 'Batter',
  batting_style VARCHAR(30) NULL,
  bowling_style VARCHAR(40) NULL,
  country VARCHAR(50) NULL,
  jersey_no VARCHAR(5) NULL,
  mobile VARCHAR(20) NULL,
  is_overseas TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (team_id),
  INDEX (name),
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE match_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  overs INT NOT NULL DEFAULT 20,
  balls_per_over INT NOT NULL DEFAULT 6,
  players_per_side INT NOT NULL DEFAULT 11,
  max_overs_bowler INT NOT NULL DEFAULT 0,
  last_man_stands TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  owner_id INT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tournaments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  format ENUM('league','knockout','league_final') NOT NULL DEFAULT 'league',
  match_type_id INT NULL,
  owner_id INT NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  venue VARCHAR(150) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tournament_teams (
  tournament_id INT NOT NULL,
  team_id INT NOT NULL,
  PRIMARY KEY (tournament_id, team_id),
  FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  scorer_id INT NULL,
  tournament_id INT NULL,
  match_type_id INT NULL,
  title VARCHAR(100) NULL,
  team1_id INT NOT NULL,
  team2_id INT NOT NULL,
  overs INT NOT NULL DEFAULT 20,
  balls_per_over INT NOT NULL DEFAULT 6,
  players_per_side INT NOT NULL DEFAULT 11,
  max_overs_bowler INT NOT NULL DEFAULT 0,
  last_man_stands TINYINT(1) NOT NULL DEFAULT 0,
  dls_g50 INT NOT NULL DEFAULT 200,
  format VARCHAR(10) NOT NULL DEFAULT 'limited',
  days TINYINT NOT NULL DEFAULT 0,
  follow_on INT NOT NULL DEFAULT 200,
  inn2_overs INT NULL,
  result_method VARCHAR(10) NULL,
  visibility ENUM('public','private') NOT NULL DEFAULT 'public',
  venue VARCHAR(150) NULL,
  match_date DATETIME NULL,
  toss_winner_id INT NULL,
  toss_decision ENUM('bat','bowl') NULL,
  status ENUM('scheduled','live','completed','abandoned') NOT NULL DEFAULT 'scheduled',
  winner_team_id INT NULL,
  result_text VARCHAR(200) NULL,
  player_of_match_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (status),
  INDEX (owner_id),
  INDEX (tournament_id),
  FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE match_players (
  match_id INT NOT NULL,
  team_id INT NOT NULL,
  player_id INT NOT NULL,
  bat_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (match_id, player_id),
  INDEX (player_id),
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE innings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  innings_no TINYINT NOT NULL,
  batting_team_id INT NOT NULL,
  bowling_team_id INT NOT NULL,
  runs INT NOT NULL DEFAULT 0,
  wickets INT NOT NULL DEFAULT 0,
  legal_balls INT NOT NULL DEFAULT 0,
  extras INT NOT NULL DEFAULT 0,
  target INT NULL,
  max_overs INT NOT NULL DEFAULT 20,
  start_overs INT NOT NULL DEFAULT 20,
  dls TINYINT(1) NOT NULL DEFAULT 0,
  is_super TINYINT(1) NOT NULL DEFAULT 0,
  follow_on TINYINT(1) NOT NULL DEFAULT 0,
  declared TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('live','completed') NOT NULL DEFAULT 'live',
  striker_id INT NULL,
  non_striker_id INT NULL,
  bowler_id INT NULL,
  UNIQUE KEY (match_id, innings_no),
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE innings_batters (
  innings_id INT NOT NULL,
  player_id INT NOT NULL,
  position INT NOT NULL,
  PRIMARY KEY (innings_id, player_id),
  FOREIGN KEY (innings_id) REFERENCES innings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE balls (
  id INT AUTO_INCREMENT PRIMARY KEY,
  innings_id INT NOT NULL,
  seq INT NOT NULL,
  over_no INT NOT NULL,
  ball_no INT NOT NULL,
  batsman_id INT NOT NULL,
  non_striker_id INT NULL,
  bowler_id INT NOT NULL,
  runs_bat INT NOT NULL DEFAULT 0,
  extra_type ENUM('wd','nb','b','lb') NULL,
  extra_runs INT NOT NULL DEFAULT 0,
  total_runs INT NOT NULL DEFAULT 0,
  is_legal TINYINT(1) NOT NULL DEFAULT 1,
  is_bouncer TINYINT(1) NOT NULL DEFAULT 0,
  is_wicket TINYINT(1) NOT NULL DEFAULT 0,
  wicket_type VARCHAR(30) NULL,
  dismissed_id INT NULL,
  fielder_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (innings_id, seq),
  INDEX (batsman_id),
  INDEX (bowler_id),
  FOREIGN KEY (innings_id) REFERENCES innings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE interruptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  innings_no TINYINT NOT NULL,
  legal_balls INT NOT NULL,
  wickets INT NOT NULL,
  overs_before INT NOT NULL,
  overs_after INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (match_id),
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE academy_progress (
  user_id INT NOT NULL,
  lesson_id VARCHAR(20) NOT NULL,
  best_correct INT NOT NULL DEFAULT 0,
  total INT NOT NULL DEFAULT 0,
  stars TINYINT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  xp INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE follows (
  follower_id INT NOT NULL,
  followee_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, followee_id),
  INDEX (followee_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Match formats (system defaults; users can add their own)
INSERT INTO match_types (name, overs, balls_per_over, players_per_side, max_overs_bowler, last_man_stands, description, is_system) VALUES
('T20', 20, 6, 11, 4, 0, '20 overs a side, max 4 overs per bowler', 1),
('ODI', 50, 6, 11, 10, 0, '50 overs a side, max 10 overs per bowler', 1),
('T10', 10, 6, 11, 2, 0, '10 overs a side, max 2 overs per bowler', 1),
('Box Cricket', 6, 6, 8, 2, 0, '6 overs, 8 a side, max 2 overs per bowler', 1),
('Gully Cricket', 5, 6, 6, 0, 1, '5 overs, 6 a side, last man stands, no bowling limit', 1),
('Backyard (Kids)', 3, 6, 3, 0, 1, '3 overs, up to 3 a side, last man stands', 1),
('Custom', 8, 6, 11, 0, 0, 'Set your own overs and players', 1);

-- v1.21 match photos (timeline)
CREATE TABLE match_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  user_id INT NULL,
  who VARCHAR(100) NULL,
  file VARCHAR(120) NOT NULL,
  thumb VARCHAR(120) NOT NULL,
  width SMALLINT NOT NULL DEFAULT 0,
  height SMALLINT NOT NULL DEFAULT 0,
  bytes INT NOT NULL DEFAULT 0,
  caption VARCHAR(200) NULL,
  score VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (match_id), INDEX (user_id),
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- v1.22 club leagues (academies.league_json + kind are added by ensure_schema, which also creates the academies table)
ALTER TABLE players ADD COLUMN gender CHAR(1) NULL, ADD COLUMN birth_year SMALLINT NULL;
ALTER TABLE teams ADD COLUMN academy_id INT NULL, ADD COLUMN league_group VARCHAR(60) NULL, ADD COLUMN league_cat VARCHAR(40) NULL, ADD INDEX idx_team_academy (academy_id);
ALTER TABLE tournaments ADD COLUMN academy_id INT NULL, ADD COLUMN visibility VARCHAR(10) NOT NULL DEFAULT 'public', ADD INDEX idx_tour_academy (academy_id);
