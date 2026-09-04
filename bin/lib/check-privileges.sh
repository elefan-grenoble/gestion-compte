#!/usr/bin/env bash
#
# Static privilege check for the DATABASE_URL account used by
# export-anonymized-db.sh.
#
# Four things the script does against a live source, found by running it
# against a real one:
#
#   1. reading the source's rows                         -> SELECT
#   2. `--routines --events` dumping its views/triggers/
#      events                                             -> SHOW VIEW,
#                                                              TRIGGER, EVENT
#   3. restoring into a scratch database, granted broadly
#      as ALL PRIVILEGES rather than enumerated because
#      both the restore and the anonymizer write there
#      freely                                              -> ALL PRIVILEGES
#                                                              on
#                                                              `<db>_anon_%`
#                                                              and
#                                                              `<db>_verify_%`
#   4. restoring a view/trigger/routine whose DEFINER
#      names an account other than the one running the
#      export — the case for anything taken from
#      production                                          -> SET USER
#                                                              (MariaDB 11+;
#                                                              SUPER on
#                                                              older servers)
#
# This reads `SHOW GRANTS FOR CURRENT_USER()` — a metadata statement, not
# a probe against the source's tables — and pattern-matches the
# requirements above against it. It cannot see a privilege granted
# through a role (MariaDB 10.5+): a false "missing" is possible there.
# Skip it with --skip-privilege-check if that is your setup; the export's
# own steps still catch a genuinely under-privileged account, just later
# and with a less specific error.
#
#   check_export_privileges <db-name> <<< "$(SHOW GRANTS output)"
#
# Prints one missing requirement per line to stdout; prints nothing and
# returns 0 when everything required is present.

check_privileges_escape() {
    printf '%s' "$1" | sed -e 's/[][\\.*^$\/]/\\&/g'
}

check_export_privileges() {
    local db_name="$1" escaped grants missing=0
    escaped="$(check_privileges_escape "${db_name}")"
    grants="$(cat)"

    # A superuser-shaped grant satisfies everything below; short-circuit
    # rather than make every check also match it.
    if printf '%s\n' "${grants}" | grep -qE 'GRANT ALL PRIVILEGES ON \*\.\*'; then
        return 0
    fi

    require_on_db() {
        local keyword="$1"
        # [^\`]* rather than [^ ]* between "GRANT" and the keyword: a
        # line granting several privileges at once (the common case,
        # e.g. "GRANT SELECT, SHOW VIEW ON ...") has spaces and commas
        # ahead of any keyword but the first.
        printf '%s\n' "${grants}" | grep -qiE "GRANT [^\`]*\b${keyword}\b[^\`]*ON \`?${escaped}\`?\.\*" \
            || { printf '%s on `%s`\n' "${keyword}" "${db_name}"; missing=1; }
    }

    require_on_db 'SELECT'
    require_on_db 'SHOW VIEW'
    require_on_db 'TRIGGER'
    require_on_db 'EVENT'

    printf '%s\n' "${grants}" | grep -qiE "GRANT ALL PRIVILEGES ON \`?${escaped}_anon_%\`?\.\*" \
        || { printf 'ALL PRIVILEGES on `%s_anon_%%`\n' "${db_name}"; missing=1; }

    printf '%s\n' "${grants}" | grep -qiE "GRANT ALL PRIVILEGES ON \`?${escaped}_verify_%\`?\.\*" \
        || { printf 'ALL PRIVILEGES on `%s_verify_%%`\n' "${db_name}"; missing=1; }

    printf '%s\n' "${grants}" | grep -qiE 'GRANT [^\`]*\bSET USER\b[^\`]*ON \*\.\*' \
        || printf '%s\n' "${grants}" | grep -qiE 'GRANT [^\`]*\bSUPER\b[^\`]*ON \*\.\*' \
        || { printf 'SET USER (or SUPER, on older servers) on *.*\n'; missing=1; }

    return "${missing}"
}
