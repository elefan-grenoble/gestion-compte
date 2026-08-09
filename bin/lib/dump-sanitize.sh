#!/usr/bin/env bash
#
# Rewrites a dump so it can be restored into a database whose name is not
# the one it was taken from.
#
# Two things in a dump are tied to where it came from, and both bite the
# person who receives it:
#
#   1. Views. A view whose DEFINER names an account that does not exist
#      on the server is stored with its table references expanded to
#      `db`.`table`.`column` in the SELECT list and the ON clauses,
#      while the FROM clause keeps the bare table name. Dumped with
#      --single-transaction — which the export uses — that expansion
#      reaches the dump verbatim, so a view taken from `a` and restored
#      into `b` reads
#
#          select `a`.`t`.`c` from `t` ...
#
#      where `t` resolves to `b`.`t` — a table that is not the one the
#      column names, so the restore fails with "Unknown column". Both
#      conditions hold here: production views carry a DEFINER from the
#      production server, and the export always dumps from a scratch
#      database whose name is generated per run and dropped afterwards.
#      Without this pass every dump carrying a view is born broken.
#
#      (Dropping --single-transaction also avoids it, but that leans on
#      an implicit server behaviour rather than saying what we mean, and
#      it would not address the DEFINER below.)
#
#   2. The DEFINER. It names an account on the source server, which does
#      not exist on the machine the dump is handed to; querying the view
#      there fails with "The user specified as a definer does not exist".
#      Dropping the clause lets the view run as whoever restored it.
#
# Reads a dump on stdin, writes the rewritten dump on stdout.
#
#   dump_sanitize <source-database-name>

# Escapes the characters that would otherwise be read as sed syntax, so
# that a database name containing one of them is matched literally.
dump_sanitize_escape() {
    printf '%s' "$1" | sed -e 's/[][\\.*^$\/]/\\&/g'
}

dump_sanitize() {
    local source_db="$1" escaped
    escaped="$(dump_sanitize_escape "${source_db}")"

    sed -e "s/\`${escaped}\`\.//g" \
        -e 's/DEFINER=`[^`]*`@`[^`]*` //g'
}
