#!/bin/bash

# Git Repository Setup Script for Ghidar
# This script initializes Git repository and sets up version control

set -e

echo "🔧 Setting up Git repository for Ghidar"
echo "======================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check if Git is installed
if ! command -v git &> /dev/null; then
    echo -e "${YELLOW}⚠️  Git is not installed. Please install Git first.${NC}"
    exit 1
fi

# Get project root directory
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

# Check if already a Git repository
if [ -d ".git" ]; then
    echo -e "${YELLOW}⚠️  Git repository already exists${NC}"
    echo "Current Git status:"
    git status --short
    echo ""
    read -p "Do you want to continue with setup? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 0
    fi
else
    # Initialize Git repository
    echo "Initializing Git repository..."
    git init
    echo -e "${GREEN}✅ Git repository initialized${NC}"
fi

# Create main branch if it doesn't exist
if ! git show-ref --verify --quiet refs/heads/main; then
    echo "Creating main branch..."
    git checkout -b main 2>/dev/null || git branch -M main
    echo -e "${GREEN}✅ Main branch created${NC}"
fi

# Create develop branch
if ! git show-ref --verify --quiet refs/heads/develop; then
    echo "Creating develop branch..."
    git checkout -b develop 2>/dev/null || git checkout develop 2>/dev/null || true
    echo -e "${GREEN}✅ Develop branch created${NC}"
fi

# Check if there are uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo ""
    echo "Staging all files for initial commit..."
    git add .
    
    echo ""
    read -p "Enter commit message (or press Enter for default): " commit_msg
    if [ -z "$commit_msg" ]; then
        commit_msg="Initial commit: Ghidar deployment safety setup"
    fi
    
    echo "Creating initial commit..."
    git commit -m "$commit_msg"
    echo -e "${GREEN}✅ Initial commit created${NC}"
else
    echo -e "${GREEN}✅ Repository is clean${NC}"
fi

# Ask about remote repository
echo ""
read -p "Do you want to set up a remote repository? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    read -p "Enter remote repository URL (e.g., https://github.com/user/repo.git): " remote_url
    if [ ! -z "$remote_url" ]; then
        # Remove existing origin if present
        git remote remove origin 2>/dev/null || true
        
        # Add new remote
        git remote add origin "$remote_url"
        echo -e "${GREEN}✅ Remote repository configured: $remote_url${NC}"
        
        echo ""
        read -p "Do you want to push to remote now? (y/n) " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            echo "Pushing to remote..."
            git push -u origin main
            git push -u origin develop
            echo -e "${GREEN}✅ Pushed to remote repository${NC}"
        fi
    fi
fi

echo ""
echo -e "${GREEN}🎉 Git setup completed!${NC}"
echo ""
echo "Current branches:"
git branch -a
echo ""
echo "Next steps:"
echo "1. Make changes to your code"
echo "2. Commit changes: git add . && git commit -m 'Your message'"
echo "3. Push to remote: git push origin main"
echo ""

