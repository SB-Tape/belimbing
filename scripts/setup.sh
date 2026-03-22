#!/usr/bin/env bash
# Belimbing Environment Setup
#
# Orchestrates modular setup steps for the Belimbing (BLB) project.
# Each component (PostgreSQL, Caddy, etc.) has its own independent script
# in setup-steps/ that can be run standalone or called by this orchestrator.
#
# Usage: ./scripts/setup.sh [local|staging|production|testing] [OPTIONS]
#
# Options:
#   local|staging|production|testing - Laravel APP_ENV value (default: local)
#   --quick                          - Skip interactive prompts, use defaults
#   --check                          - Pre-flight validation only (no installation)
#   --auto-install                   - Auto-install missing dependencies
#   --report                         - Generate installation report
#
# Examples:
#   ./scripts/setup.sh local                # Interactive local setup
#   ./scripts/setup.sh local --quick        # Non-interactive local setup
#   ./scripts/setup.sh local --check        # Validate requirements only
#   ./scripts/setup.sh local --auto-install # Auto-install dependencies

set -euo pipefail

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Source shared utilities
# shellcheck source=/shared/colors.sh
source "$SCRIPT_DIR/shared/colors.sh" 2>/dev/null || true
# shellcheck source=/shared/runtime.sh
source "$SCRIPT_DIR/shared/runtime.sh" 2>/dev/null || true
# shellcheck source=/shared/config.sh
source "$SCRIPT_DIR/shared/config.sh" 2>/dev/null || true
# shellcheck source=/shared/validation.sh
source "$SCRIPT_DIR/shared/validation.sh" 2>/dev/null || true
# shellcheck source=/shared/interactive.sh
source "$SCRIPT_DIR/shared/interactive.sh" 2>/dev/null || true

# Parse arguments
APP_ENV="${1:-local}"
QUICK_MODE=false
CHECK_ONLY=false
AUTO_INSTALL=false
GENERATE_REPORT=false

for arg in "$@"; do
    case "$arg" in
        --quick)
            QUICK_MODE=true
            ;;
        --check)
            CHECK_ONLY=true
            ;;
        --auto-install)
            AUTO_INSTALL=true
            ;;
        --report)
            GENERATE_REPORT=true
            ;;
        local|staging|production|testing)
            APP_ENV="$arg"
            ;;
        *) ;;
    esac
done

# Validate environment value (Laravel standard APP_ENV values)
if [[ ! "$APP_ENV" =~ ^(local|staging|production|testing)$ ]]; then
    echo -e "${RED}✗ Invalid APP_ENV: '$APP_ENV'${NC}"
    echo -e "${YELLOW}Valid values: local, staging, production, testing${NC}"
    exit 1
fi

# Installation tracking
INSTALL_START_TIME=$(date +%s)
declare -a INSTALLED_PACKAGES=()
declare -a CONFIGURED_SERVICES=()
ROLLBACK_POINTS=()

# Setup dependencies function
setup_dependencies() {
    if [[ "$AUTO_INSTALL" = false ]]; then
        return 0
    fi

    echo -e "${CYAN}Checking and installing system dependencies...${NC}"
    echo ""

    local os_type
    os_type=$(detect_os 2>/dev/null || echo "unknown")
    local missing_packages=()

    # Check and install Git
    if ! command_exists git; then
        missing_packages+=("git")
    fi

    # Check and install PHP (handled by 20-php.sh, but we can pre-check)
    if ! command_exists php; then
        missing_packages+=("php")
    fi

    # Check and install Composer (handled by 20-php.sh)
    if ! command_exists composer; then
        missing_packages+=("composer")
    fi

    # Check JavaScript runtime (handled by 30-js.sh)
    if ! command_exists bun && ! command_exists node; then
        missing_packages+=("js-runtime")
    fi

    if [[ ${#missing_packages[@]} -eq 0 ]]; then
        echo -e "${GREEN}✓${NC} All dependencies available"
        return 0
    fi

    echo -e "${YELLOW}Missing dependencies detected: ${missing_packages[*]}${NC}"
    echo -e "${CYAN}These will be installed by the setup steps.${NC}"
    echo ""
    return 0
}

# Validate system requirements
validate_system_requirements() {
    echo -e "${CYAN}Running pre-flight system check...${NC}"
    echo ""

    if [[ -f "$SCRIPT_DIR/check-requirements.sh" ]]; then
        if bash "$SCRIPT_DIR/check-requirements.sh" "$APP_ENV"; then
            echo ""
            return 0
        else
            echo ""
            if [[ "$QUICK_MODE" = false ]] && [[ -t 0 ]]; then
                if ! ask_yes_no "Some requirements are missing. Continue anyway?" "n"; then
                    exit 1
                fi
            else
                echo -e "${YELLOW}Continuing despite requirement warnings...${NC}"
            fi
            return 0
        fi
    else
        echo -e "${YELLOW}⚠${NC} check-requirements.sh not found, skipping validation"
        return 0
    fi
}

# Run setup with rollback capability
run_setup_with_rollback() {
    local step_script=$1
    local description=$2

    # Create rollback point
    local rollback_id
    rollback_id=$(date +%s)
    ROLLBACK_POINTS+=("$rollback_id:$step_script")

    # Run the step
    if bash "$step_script" "$APP_ENV"; then
        return 0
    else
        echo -e "${RED}✗${NC} Step failed: $description"
        echo -e "${YELLOW}Rollback point created: $rollback_id${NC}"
        return 1
    fi
}

# Show progress
show_progress() {
    local current=$1
    local total=$2
    local description=$3
    local start_time=$4

    local elapsed
    elapsed=$(($(date +%s) - start_time))
    local percent
    percent=$((current * 100 / total))
    local eta=0

    if [[ "$current" -gt 0 ]]; then
        local avg_time
        avg_time=$((elapsed / current))
        eta=$((avg_time * (total - current)))
    fi

    printf "\r${CYAN}Progress:${NC} [%3d%%] Step %d/%d: %s (Elapsed: %ds, ETA: %ds)" \
        "$percent" "$current" "$total" "$description" "$elapsed" "$eta"
    return 0
}

# Generate installation report
generate_installation_report() {
    if [[ "$GENERATE_REPORT" = false ]] && [[ ${#INSTALLED_PACKAGES[@]} -eq 0 ]]; then
        return 0
    fi

    local report_file
    report_file="$PROJECT_ROOT/storage/app/.devops/installation-report-$(date +%Y%m%d-%H%M%S).txt"

    {
        echo "Belimbing Installation Report"
        echo "Generated: $(date)"
        echo "Environment: $APP_ENV"
        echo ""
        echo "=== Installed Packages ==="
        for pkg in "${INSTALLED_PACKAGES[@]}"; do
            echo "  - $pkg"
        done
        echo ""
        echo "=== Configured Services ==="
        for svc in "${CONFIGURED_SERVICES[@]}"; do
            echo "  - $svc"
        done
        echo ""
        echo "=== Installation Time ==="
        local total_time
        total_time=$(($(date +%s) - INSTALL_START_TIME))
        echo "Total time: ${total_time}s"
    } > "$report_file"

    echo -e "${CYAN}Installation report saved to: ${report_file}${NC}"
    return 0
}

# If check-only mode, run validation and exit
if [[ "$CHECK_ONLY" = true ]]; then
    validate_system_requirements
    exit 0
fi

# Display banner
clear
print_section_banner "Belimbing Environment Setup ($APP_ENV)"

# Run pre-flight validation
if ! validate_system_requirements; then
    exit 1
fi

# Setup dependencies if auto-install is enabled
setup_dependencies

# Auto-discover setup steps from setup-steps/ directory
# Each step file must have a "# Title: ..." line in its header
declare -a STEPS=()

# Find all .sh files in setup-steps/, sorted by name
while IFS= read -r step_file; do
    script_name=$(basename "$step_file")

    # Extract title from "# Title: ..." line in the file
    title=$(grep "^# Title:" "$step_file" | head -1 | sed 's/^# Title: //')

    if [[ -z "$title" ]]; then
        # Fallback if no Title line found
        title="Setup Step"
    fi

    STEPS+=("$script_name:$title")
done < <(find "$SCRIPT_DIR/setup-steps" -name "*.sh" -type f | sort)

# Verify we found steps
if [[ ${#STEPS[@]} -eq 0 ]]; then
    echo -e "${RED}✗ No setup steps found in $SCRIPT_DIR/setup-steps/${NC}"
    exit 1
fi

# Track results
declare -a COMPLETED=()
declare -a FAILED=()

# Display setup plan
echo -e "${CYAN}Setup Steps:${NC}"
step_num=1
for step in "${STEPS[@]}"; do
    IFS=':' read -r script description <<< "$step"
    echo -e "  ${step_num}. ${description}"
    ((step_num++))
done
echo ""

if [[ "$QUICK_MODE" = false ]] && [[ -t 0 ]]; then
    read -r -p "Press Enter to begin, or Ctrl+C to cancel... "
    echo ""
fi

# Run each step
step_num=1
STEP_START_TIME=$(date +%s)
for step in "${STEPS[@]}"; do
    IFS=':' read -r script description <<< "$step"

    step_script="$SCRIPT_DIR/setup-steps/$script"
    if [[ ! -f "$step_script" ]]; then
        echo -e "${RED}✗${NC} Step script not found: $step_script"
        FAILED+=("$description")
        ((step_num++))
        continue
    fi

    # Show progress
    show_progress "$step_num" "${#STEPS[@]}" "$description" "$STEP_START_TIME"
    echo ""

    # Run the step with rollback capability
    if run_setup_with_rollback "$step_script" "$description"; then
        echo ""
        echo -e "${GREEN}✓${NC} ${description} - Complete"
        COMPLETED+=("$description")
        CONFIGURED_SERVICES+=("$description")
    else
        echo ""
        echo -e "${RED}✗${NC} ${description} - Failed"
        FAILED+=("$description")

        # Ask if user wants to continue
        if [[ "$QUICK_MODE" = false ]] && [[ -t 0 ]]; then
            echo ""
            if ! ask_yes_no "Continue with remaining steps?" "n"; then
                echo ""
                echo -e "${YELLOW}Setup interrupted${NC}"
                break
            fi
        else
            echo -e "${YELLOW}Stopping due to failure${NC}"
            break
        fi
    fi

    echo ""
    ((step_num++))
done

# Display summary
echo ""
print_section_banner "Setup Summary"

if [[ ${#COMPLETED[@]} -gt 0 ]]; then
    echo -e "${GREEN}✓ Completed (${#COMPLETED[@]}):${NC}"
    for step in "${COMPLETED[@]}"; do
        echo -e "  • $step"
    done
    echo ""
fi

if [[ ${#FAILED[@]} -gt 0 ]]; then
    echo -e "${RED}✗ Failed (${#FAILED[@]}):${NC}"
    for step in "${FAILED[@]}"; do
        echo -e "  • $step"
    done
    echo ""
fi

# Generate installation report
generate_installation_report

# Final status
if [[ ${#FAILED[@]} -eq 0 ]] && [[ ${#COMPLETED[@]} -eq ${#STEPS[@]} ]]; then
    total_time=$(($(date +%s) - INSTALL_START_TIME))
    echo -e "${GREEN}✓ Setup Complete! 🎉${NC}"
    echo ""
    echo -e "${CYAN}Installation Summary:${NC}"
    echo -e "  • Total time: ${total_time} seconds"
    echo -e "  • Steps completed: ${#COMPLETED[@]}"
    echo -e "  • Configuration: ${CYAN}.env${NC} (you can edit this file to customize settings)"
    echo ""

    # Cleanup temporary setup state file
    # Source config.sh to get get_setup_state_file function
    # shellcheck source=shared/config.sh
    source "$SCRIPT_DIR/shared/config.sh" 2>/dev/null || true
    if command -v get_setup_state_file >/dev/null 2>&1; then
        state_file=$(get_setup_state_file)
        if [[ -f "$state_file" ]]; then
            rm -f "$state_file"
        fi
    elif [[ -f "$PROJECT_ROOT/storage/app/.devops/setup.env" ]]; then
        # Fallback to direct path if function not available
        rm -f "$PROJECT_ROOT/storage/app/.devops/setup.env"
    fi

    # Offer to start the app
    if [[ -t 0 ]]; then
        echo ""
        if read -r -p "Start Belimbing now? (y/n) [y]: " -n 1; then
            echo ""
            if [[ $REPLY =~ ^[Yy]$ ]] || [[ -z $REPLY ]]; then
                echo ""
                exec "$SCRIPT_DIR/start-app.sh"
            fi
        fi
        echo ""
    else
        echo -e "${CYAN}Start the app:${NC}"
        echo -e "  ${CYAN}./scripts/start-app.sh${NC}"
        echo ""
    fi
    exit 0
else
    echo -e "${YELLOW}⚠ Setup Incomplete${NC}"
    echo ""
    echo -e "${CYAN}Retry failed steps:${NC}"
    for step in "${STEPS[@]}"; do
        IFS=':' read -r script description <<< "$step"
        for failed in "${FAILED[@]}"; do
            if [[ "$failed" = "$description" ]]; then
                echo -e "  ${CYAN}./scripts/setup-steps/$script $APP_ENV${NC}"
            fi
        done
    done
    echo ""
    echo -e "${CYAN}Or re-run full setup:${NC}"
    echo -e "  ${CYAN}./scripts/setup.sh $APP_ENV${NC}"
    echo ""
    exit 1
fi
