# MySQL migrations #
This applucation helps you to migrate your MySQL schema.

# Usage #
You need:
 * A config/environment.yml with your db credentials.

```
db:
  host: localhost
  schema: incident_mgr
  user: root
  password:
```
 * Create the empty schema in your db.
 * Have a db/ directory in your root directory.
 * Have a db/schema.sql file with your base schema. That can contain the sql to create the first version of your db.
 * Have a db/deltas directory.
 * Add there s many v*.sql as you need, where * is an integer. Each of these sql will be the next delta to apply.

You can have as many sql queries as you want in each delta or schema file.

# Example #
My db/schema.sql could be:
```sql
create table example (id int);
```
My db/deltas/v1.sql:
```sql
create table example_two (id int);
```
Finally a second delta db/deltas/v2.sql:
```sql
alter table example add name varchar(65);
```
