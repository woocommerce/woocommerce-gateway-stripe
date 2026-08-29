#!/usr/bin/env bash

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

shell_name="$(basename "${SHELL:-}")"
case "$shell_name" in
    zsh)  rc_file="$HOME/.zshrc" ;;
    bash) rc_file="$HOME/.bashrc" ;;
    *)    rc_file="$HOME/.profile" ;;
esac

alias_line="alias gwt=\". $script_dir/gwt.sh\""
if grep -qxF "$alias_line" "$rc_file" 2>/dev/null; then
    echo "→ Alias already configured in $rc_file"
else
    echo "$alias_line" >> "$rc_file"
fi


# Tab-completion for branch names: gwt fix/<TAB> completes from real branches.
case "$shell_name" in
    zsh)  completion_line="compdef _gwt gwt" ;;
    bash) completion_line="complete -F _gwt gwt" ;;
    *)    completion_line="" ;;
esac

if [ -z "$completion_line" ]; then
    echo "→ Completion skipped: $shell_name is not supported"
elif grep -qxF "$completion_line" "$rc_file" 2>/dev/null; then
    echo "→ Completion already configured in $rc_file"
else
    case "$shell_name" in
        zsh)
            cat >> "$rc_file" <<'EOF'

# gwt branch-name completion
_gwt() {
  local repo_root branches
  repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || return
  branches=($(git -C "$repo_root" branch -a --format='%(refname:short)' | sed 's|^origin/||' | sort -u))
  compadd -a branches
}
compdef _gwt gwt
EOF
            ;;
        bash)
            cat >> "$rc_file" <<'EOF'

# gwt branch-name completion
_gwt() {
  local repo_root branches cur
  cur="${COMP_WORDS[COMP_CWORD]}"
  repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || return
  branches=$(git -C "$repo_root" branch -a --format='%(refname:short)' | sed 's|^origin/||' | sort -u)
  COMPREPLY=( $(compgen -W "$branches" -- "$cur") )
}
complete -F _gwt gwt
EOF
            ;;
    esac
fi

echo "✔ Setup completed, please restart your shell to start using 'gwt'"
