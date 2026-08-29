CREATE TABLE listing_stats (
  listing_id INT NOT NULL,
  event VARCHAR(20) NOT NULL,
  day DATE NOT NULL,
  cnt INT NOT NULL DEFAULT 0,
  PRIMARY KEY (listing_id, event, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
