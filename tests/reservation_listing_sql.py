"""Exercise the listing SQL with SQLite fixtures; run with python3 tests/reservation_listing_sql.py.

This checks selection semantics, not PHP execution or MySQL compatibility.
"""
import re
import sqlite3
from pathlib import Path

source = (Path(__file__).resolve().parents[1] / 'app/Repositories/ReservationRepository.php').read_text()
listing = re.search(r'\$source = "(.*?)";', source, re.S).group(1)
db = sqlite3.connect(':memory:')
db.executescript('''
CREATE TABLE reservas(id,codigo,nome_cliente,email,telefone,checkin,checkout,status,origem,created_at);
CREATE TABLE calendar_sources(id,nome,provider,ativo);
CREATE TABLE calendar_external_events(id,source_id,external_uid,summary,starts_at,ends_at,status,deleted_at,created_at);
INSERT INTO calendar_sources VALUES(1,'Airbnb','AIRBNB',1),(2,'Booking','BOOKING',1);
INSERT INTO reservas VALUES(1,'LOCAL','Ana','','','2026-11-01','2026-11-02','RESERVA_CONFIRMADA','SITE_DIRETO','2026-10-01');
''')
# Consecutive blocked nights must not turn into reservations.
titles = ['Airbnb (Not available)', 'Not available', ' BLOCKED ', 'Closed', 'Indisponível']
for i, title in enumerate(titles, 1):
    db.execute('INSERT INTO calendar_external_events VALUES(?,?,?,?,?,?,?,?,?)',
               (i, 1, f'block-{i}', title, f'2026-11-{i:02}', f'2026-11-{i+1:02}', 'CONFIRMED', None, '2026-10-01'))
# Adjacent genuine reservations must remain separate, including one-night stays.
for i, provider, title, status, removed in [
    (10, 1, 'Reserved', 'CONFIRMED', None),
    (11, 1, 'Reserved', 'CONFIRMED', None),
    (12, 2, 'Booking reservation', 'CONFIRMED', None),
    (13, 1, 'Reserved', 'CANCELLED', None),
    (14, 1, 'Reserved', 'CONFIRMED', '2026-11-20'),
]:
    db.execute('INSERT INTO calendar_external_events VALUES(?,?,?,?,?,?,?,?,?)',
               (i, provider, f'reservation-{i}', title, f'2026-11-{i:02}', f'2026-11-{i+1:02}', status, removed, '2026-10-01'))
rows = db.execute('SELECT record_type,id,status FROM ' + listing).fetchall()
assert len(rows) == 6, rows
assert {row[1] for row in rows if row[0] == 'external'} == {10, 11, 12, 13, 14}
assert ('external', 13, 'CANCELADA') in rows
assert ('external', 14, 'ICAL_REMOVIDO') in rows
assert db.execute('SELECT COUNT(*) FROM ' + listing + " WHERE origem='AIRBNB'").fetchone()[0] == 4
assert db.execute('SELECT COUNT(*) FROM calendar_external_events').fetchone()[0] == 10
print('OK: blocks excluded; adjacent stays, Booking, cancellations and history preserved.')
