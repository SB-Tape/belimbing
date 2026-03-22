#!/usr/bin/env bash
# scripts/stop-docker.sh
# Title: Stop Belimbing Docker Services
# Purpose: Stop Belimbing services running in Docker
# Usage: ./scripts/stop-docker.sh [local|staging|production|testing] [--volumes] [--no-cleanup]
#
# This script:
# - Stops all Docker Compose services
# - Optionally removes volumes (--volumes flag)
# - Automatically cleans up orphaned containers/networks (use --no-cleanup to disable)
#
# When to use --volumes:
# - Starting fresh: You want to completely reset the database and all persistent data
# - Testing: You need a clean database state for testing
# - Troubleshooting: Database corruption or migration issues require a fresh start
# - Development reset: You want to clear all data and start from scratch
# WARNING: This permanently deletes ALL database data, uploaded files, and other
#          persistent data stored in Docker volumes. The script will prompt for
#          confirmation in interactive mode. Use with extreme caution!
# RESTRICTION: Volume deletion is NOT allowed in production environment for safety.
#              Use Docker CLI commands manually if you need to remove production volumes.
#
# Cleanup runs by default (safe and prevents issues):
# - Automatically removes containers in bad states (Created, Exited, Restarting)
# - Automatically fixes networks with incorrect labels
# - Prevents accumulation of orphaned resources that cause startup failures
# - Safe: only removes containers/networks that are already broken
# Use --no-cleanup to disable if you need to inspect orphaned containers
#
# Why this script exists:
#
# 1. Recovery from crashes: If start-docker.sh was killed or Docker crashed,
#    containers may be left running. This script allows manual cleanup.
#
# 2. Stopping from different terminal: If start-docker.sh is running in another
#    terminal, this script can stop services from any terminal.
#
# 3. Explicit stopping: Better UX than manually running docker compose commands
#    with correct profile and project name.
#
# 4. Debugging/cleanup: Useful for force-cleaning stuck containers during
#    development and troubleshooting.

set -euo pipefail

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Source shared utilities
# shellcheck source=shared/colors.sh
source "$SCRIPT_DIR/shared/colors.sh" 2>/dev/null || true
# shellcheck source=shared/validation.sh
source "$SCRIPT_DIR/shared/validation.sh" 2>/dev/null || true
# shellcheck source=shared/runtime.sh
source "$SCRIPT_DIR/shared/runtime.sh" 2>/dev/null || true
# shellcheck source=shared/config.sh
source "$SCRIPT_DIR/shared/config.sh" 2>/dev/null || true
# shellcheck source=shared/interactive.sh
source "$SCRIPT_DIR/shared/interactive.sh" 2>/dev/null || true

# Parse arguments
APP_ENV="local"
REMOVE_VOLUMES=false
CLEANUP_ORPHANS=true  # Run by default - safe and prevents issues

# Parse arguments and flags
while [[ $# -gt 0 ]]; do
    case $1 in
        local|staging|production|testing)
            APP_ENV="$1"
            shift
            ;;
        --volumes)
            REMOVE_VOLUMES=true
            shift
            ;;
        --no-cleanup)
            CLEANUP_ORPHANS=false
            shift
            ;;
        --cleanup)
            CLEANUP_ORPHANS=true
            shift
            ;;
        *)
            echo -e "${RED}✗${NC} Unknown option: $1" >&2
            echo -e "Usage: $0 [ENVIRONMENT] [--volumes] [--no-cleanup]" >&2
            echo -e "  ENVIRONMENT: local (default), staging, production, testing" >&2
            echo -e "  --volumes: Remove volumes (deletes all data)" >&2
            echo -e "  --no-cleanup: Skip automatic cleanup of orphaned containers/networks" >&2
            exit 1
            ;;
    esac
done

# Get Docker Compose profile based on environment
get_compose_profile() {
    if [[ "$APP_ENV" = "production" ]]; then
        echo "prod"
    else
        echo "dev"
    fi
    return 0
}

# Get Docker Compose project name
get_compose_project_name() {
    # Use consistent project name matching codebase convention
    echo "blb"
    return 0
}

# Run docker compose with correct profile
# Usage: run_compose [args...]
run_compose() {
    local project_name
    project_name=$(get_compose_project_name)
    local profile
    profile=$(get_compose_profile)
    local compose_file="$PROJECT_ROOT/docker/docker-compose.yml"
    local env_file="$PROJECT_ROOT/docker/.env"

    # Build base command args
    local cmd_args=(-f "$compose_file" --env-file "$env_file" --profile "$profile" -p "$project_name")

    docker compose "${cmd_args[@]}" "$@"
}

# Clean up orphaned containers and networks
cleanup_orphans() {
    local project_name
    project_name=$(get_compose_project_name)

    echo -e "${CYAN}Cleaning up orphaned containers...${NC}"

    # Remove containers in bad states (created but not started, exited, etc.)
    local orphaned_containers
    orphaned_containers=$(docker ps -a --filter "name=${project_name}-" --format "{{.Names}}\t{{.Status}}" | \
        grep -E "(Created|Exited|Restarting)" | cut -f1 || true)

    if [[ -n "$orphaned_containers" ]]; then
        echo "$orphaned_containers" | while read -r container; do
            if [[ -n "$container" ]]; then
                echo -e "  Removing orphaned container: ${CYAN}${container}${NC}"
                docker rm -f "$container" 2>/dev/null || true
            fi
        done
        echo -e "${GREEN}✓${NC} Orphaned containers cleaned up"
    else
        echo -e "${GREEN}✓${NC} No orphaned containers found"
    fi

    # Remove network if it exists but wasn't created by compose (fixes label mismatch)
    local network_name="${project_name}_belimbing-network"
    if docker network inspect "$network_name" >/dev/null 2>&1; then
        # Check if it has compose labels - if not, remove it so compose can recreate
        local compose_label
        compose_label=$(docker network inspect "$network_name" --format '{{index .Labels "com.docker.compose.network"}}' 2>/dev/null || echo "")
        if [[ -z "$compose_label" ]] || [[ "$compose_label" != "belimbing-network" ]]; then
            echo -e "  Removing network with incorrect labels: ${CYAN}${network_name}${NC}"
            docker network rm "$network_name" 2>/dev/null || true
        fi
    fi
    return 0
}

# Stop Docker services
# Usage: stop_services [remove_volumes]
#   remove_volumes: if "true", removes volumes with -v flag
stop_services() {
    local remove_volumes="${1:-false}"

    if [[ "$remove_volumes" = "true" ]]; then
        echo -e "${CYAN}Stopping services and removing volumes...${NC}"
        if run_compose down -v; then
            echo -e "${GREEN}✓${NC} Services stopped and volumes removed"
            echo -e "${YELLOW}⚠${NC} All data has been permanently deleted"
            return 0
        fi

        echo -e "${RED}✗${NC} Failed to stop services" >&2
        return 1
    fi

    echo -e "${CYAN}Stopping services...${NC}"
    if run_compose down; then
        echo -e "${GREEN}✓${NC} Services stopped"
        return 0
    fi

    echo -e "${RED}✗${NC} Failed to stop services" >&2
    return 1
}

# Validate and confirm volume removal (safety checks)
# Returns 0 if volumes should be removed, 1 if not
validate_volume_removal() {
    # Prevent volume deletion in production for safety
    if [[ "$APP_ENV" = "production" ]]; then
        echo -e "${RED}✗${NC} Volume deletion is not allowed in production environment" >&2
        echo ""
        echo -e "${YELLOW}For safety reasons, volumes cannot be removed via this script in production.${NC}"
        echo -e "${YELLOW}If you need to remove volumes, you must do it manually using Docker CLI:${NC}"
        echo ""
        echo -e "${CYAN}Manual commands:${NC}"
        echo -e "  1. Stop services: ${CYAN}docker compose -f docker/docker-compose.yml --env-file docker/.env --profile prod -p blb down${NC}"
        echo -e "  2. List volumes: ${CYAN}docker volume ls | grep blb${NC}"
        echo -e "  3. Remove volumes: ${CYAN}docker volume rm <volume-name>${NC}"
        echo ""
        echo -e "${YELLOW}⚠${NC} ${RED}WARNING: Removing volumes will permanently delete all production data!${NC}"
        return 1
    fi

    echo -e "${RED}⚠${NC} ${RED}WARNING: This will permanently delete ALL database data!${NC}"
    echo -e "${RED}  - All PostgreSQL data will be lost${NC}"
    echo -e "${RED}  - All uploaded files will be deleted${NC}"
    echo -e "${RED}  - All cache data will be cleared${NC}"
    echo -e "${RED}  - This action cannot be undone!${NC}"
    echo ""

    # Handle non-interactive mode
    if [[ ! -t 0 ]]; then
        echo -e "${YELLOW}⚠ Non-interactive mode: Proceeding with volume deletion...${NC}"
        echo ""
    fi

    # Ask for confirmation in interactive mode
    if [[ -t 0 ]] && ! ask_yes_no "Are you sure you want to delete all data?" "n"; then
        echo -e "${YELLOW}Operation cancelled. Volumes preserved.${NC}"
        return 1
    fi

    return 0
}

# Check if Docker is available
check_docker() {
    if ! command_exists docker; then
        return 1
    fi

    if ! docker compose version >/dev/null 2>&1 && ! command_exists docker-compose; then
        return 1
    fi

    if ! docker info >/dev/null 2>&1; then
        return 1
    fi

    return 0
}

# Main function
main() {
    # Check Docker
    if ! check_docker; then
        echo -e "${RED}✗${NC} Docker is not available or daemon is not running" >&2
        exit 1
    fi

    local project_name
    project_name=$(get_compose_project_name)
    local profile
    profile=$(get_compose_profile)

    echo -e "${YELLOW}Stopping Belimbing Docker services (${APP_ENV})...${NC}"
    echo ""

    # Check if services are running
    if ! docker ps --format "{{.Names}}" | grep -q "^${project_name}-"; then
        echo -e "${YELLOW}⚠${NC} No Belimbing containers are running"

        # Still run cleanup if requested
        if [[ "$CLEANUP_ORPHANS" = true ]]; then
            cleanup_orphans
        fi

        exit 0
    fi

    # Show running containers
    echo -e "${CYAN}Running containers:${NC}"
    docker ps --filter "name=${project_name}-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" || true
    echo ""

    # Stop services
    local remove_volumes=false
    if [[ "$REMOVE_VOLUMES" = true ]]; then
        # Validate and confirm volume removal
        if ! validate_volume_removal; then
            exit 1
        fi
        remove_volumes=true
    fi

    # Stop services
    if ! stop_services "$remove_volumes"; then
        exit 1
    fi

    # Cleanup orphans if requested
    if [[ "$CLEANUP_ORPHANS" = true ]]; then
        echo ""
        cleanup_orphans
    fi

    echo ""
    echo -e "${GREEN}✓${NC} Belimbing Docker services stopped"
    echo ""
    echo -e "${CYAN}Note:${NC} Volumes are preserved (data is safe)"
    echo -e "  To remove volumes: ${CYAN}$0 $APP_ENV --volumes${NC}"
    return 0
}

# Run main function
main "$@"
