#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# SETUP SHELL ALIASES: Quick commands for token optimization
# ═══════════════════════════════════════════════════════════════════════════
#
# This script adds convenient aliases to your shell profile
#
# Usage:
#   source .dev-config/setup-aliases.sh
#   OR
#   bash .dev-config/setup-aliases.sh
# ═══════════════════════════════════════════════════════════════════════════

PROJECT_ROOT="/home/user/Schoolmanagement/college-mgmt"
SCRIPT="$PROJECT_ROOT/.dev-config/auto-optimize.sh"

# Make script executable
chmod +x "$SCRIPT"

cat << 'ALIASES'

# ═══════════════════════════════════════════════════════════════════════════
# QUICK ALIASES FOR TOKEN OPTIMIZATION
# ═══════════════════════════════════════════════════════════════════════════
#
# Add these to ~/.bashrc or ~/.zshrc to use them permanently:
#

# Token optimization shortcuts
alias feature="bash /home/user/Schoolmanagement/college-mgmt/.dev-config/auto-optimize.sh start-feature"
alias ctx="bash /home/user/Schoolmanagement/college-mgmt/.dev-config/auto-optimize.sh before-chat"
alias analyze="bash /home/user/Schoolmanagement/college-mgmt/.dev-config/auto-optimize.sh analyze"

# Graphify shortcuts (0 tokens)
alias graph="cd /home/user/Schoolmanagement/college-mgmt && graphify query"
alias gupdate="cd /home/user/Schoolmanagement/college-mgmt && graphify update ."

# Smart grep for code search (0 tokens)
alias findmodel="grep -r 'class' app/Models --include='*.php'"
alias findcontroller="grep -r 'class.*Controller' app/Http/Controllers --include='*.php'"
alias findroute="grep -n '' routes/web.php | grep"

# Development commands
alias serve="php artisan serve --port=8000"
alias migrate="php artisan migrate"
alias seed="php artisan db:seed"
alias fresh="php artisan migrate:fresh --seed"

ALIASES

echo ""
echo "═══════════════════════════════════════════════════════════════════════"
echo "COPY THESE ALIASES TO YOUR SHELL PROFILE (~/.bashrc or ~/.zshrc):"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""
echo "Then use commands like:"
echo "  feature \"elective_management\""
echo "  graph \"What models handle Student?\""
echo "  analyze \"elective\""
echo ""
