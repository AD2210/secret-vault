#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

SCRIPT_NAME="$(basename "${BASH_SOURCE[1]:-${BASH_SOURCE[0]}}")"
OPS_LOG_DIR="${OPS_LOG_DIR:-/var/log/client-secret-vault}"
OPS_LOG_FILE="${OPS_LOG_FILE:-${OPS_LOG_DIR}/ops.log}"

timestamp_utc() {
    date -u +"%Y-%m-%dT%H:%M:%SZ"
}

init_logging() {
    mkdir -p "${OPS_LOG_DIR}"
    touch "${OPS_LOG_FILE}"
}

log_message() {
    local level="$1"
    shift
    local message="$*"
    printf '%s [%s] [%s] %s\n' "$(timestamp_utc)" "${level}" "${SCRIPT_NAME}" "${message}" | tee -a "${OPS_LOG_FILE}" >&2
}

log_info() {
    log_message "INFO" "$*"
}

log_warn() {
    log_message "WARN" "$*"
}

log_error() {
    log_message "ERROR" "$*"
}

on_error_trap() {
    local exit_code="$?"
    local line_number="${BASH_LINENO[0]:-unknown}"
    log_error "Unhandled error near line ${line_number} (exit=${exit_code})."
    exit "${exit_code}"
}

setup_error_trap() {
    trap on_error_trap ERR
}

require_command() {
    local cmd="$1"
    if ! command -v "${cmd}" >/dev/null 2>&1; then
        log_error "Required command not found: ${cmd}"
        exit 1
    fi
}

load_env_file() {
    local env_file="$1"

    if [[ ! -f "${env_file}" ]]; then
        log_error "Env file not found: ${env_file}"
        exit 1
    fi

    # shellcheck disable=SC1090
    source "${env_file}"
    log_info "Loaded env file: ${env_file}"
}
