-- ---------------------------------------------------------------------------
-- Demo credentials.
--
-- The dump this project was built from shipped MD5 password hashes whose
-- plaintext nobody recorded, so the seeded accounts could not actually be used.
-- These are bcrypt hashes, matching what the application now writes, with the
-- passwords documented in the README.
--
-- Local demo values. Anything deployed anywhere real must change them.
-- ---------------------------------------------------------------------------

-- Staff. type 1 = administrator, type 2 = staff.
UPDATE `users`
   SET `password` = '$2y$12$jeQLyJxSsYQxe/j.FyYMVuHXkcsZaCUUdzOP3zPnAyHIfn2Lpp736'
 WHERE `username` = 'admin';

UPDATE `users`
   SET `password` = '$2y$12$eZumbS.11mKzTYRY00EYwe6nHmfiN09A3eiBQPG5QODPaF/tAqce6'
 WHERE `username` IN ('martha', 'andrew');

-- Every seeded customer.
UPDATE `client_list`
   SET `password` = '$2y$12$OZglD2IER.Rbpl9Y9XuTqu2knSA67aawB.xQww6iKWMRonH/S/dJO';

-- Every seeded driver; they sign in with the cab's registration code.
UPDATE `cab_list`
   SET `password` = '$2y$12$54YjmoJH.LXiJ6vPbfTcveRunFCzL2xih8BF9e5tR3JZdJd/MSqnC';
