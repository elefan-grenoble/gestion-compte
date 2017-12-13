INSERT INTO shift (start, end, max_shifters_nb) VALUES ('2017-12-26 06:00', '2017-12-26 09:00', 4);
INSERT INTO shift (start, end, max_shifters_nb) VALUES ('2017-12-26 18:00', '2017-12-26 19:30', 4);
INSERT INTO shift (start, end, max_shifters_nb) VALUES ('2017-12-26 19:30', '2017-12-26 21:00', 4);

INSERT INTO booked_shift (shift_id, shifter_id, booker_id, booked_time, is_dismissed, dismissed_time, dismissed_reason)
VALUES (2, 1, 1, '2017-12-10 21:18', false, null, null);


