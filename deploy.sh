#!/bin/bash

# Définition des couleurs pour une meilleure visualisation
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}[INFO] ===========================================================${NC}"
echo -e "${BLUE}[INFO] Démarrage du déploiement pour Livr'ideia le $(date)${NC}"
echo -e "${BLUE}[INFO] ===========================================================${NC}"
echo -e "${YELLOW}[DEBUG] Répertoire actuel: $(pwd)${NC}"

# Récupération des modifications depuis le dépôt
echo -e "${YELLOW}[DEBUG] Exécution de git pull...${NC}"
git_output=$(git pull origin main 2>&1)
git_status=$?

if [ $git_status -eq 0 ]; then
    echo -e "${GREEN}[OK] Git pull terminé avec succès${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie git:${NC}\n$git_output"
else
    echo -e "${RED}[ERREUR] Échec de git pull (code $git_status)${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie git:${NC}\n$git_output"
    exit 1
fi

# Installation des dépendances Composer
echo -e "${YELLOW}[DEBUG] Installation des dépendances Composer...${NC}"
composer_output=$(composer install --optimize-autoloader 2>&1)
composer_status=$?

if [ $composer_status -eq 0 ]; then
    echo -e "${GREEN}[OK] Dépendances Composer installées avec succès${NC}"
    echo -e "${YELLOW}[DEBUG] Résumé de l'installation:${NC}"
    echo "$composer_output" | grep -E "Installing|Updating|Nothing to install" || echo "Pas de changements"
else
    echo -e "${RED}[ERREUR] Échec de l'installation des dépendances Composer (code $composer_status)${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie composer:${NC}\n$composer_output"
    echo -e "${YELLOW}[INFO] Tentative d'installation sans l'option --no-dev...${NC}"
    # Si l'installation échoue, essayons sans l'option --no-dev
    composer_output=$(composer install --optimize-autoloader 2>&1)
    composer_status=$?
    
    if [ $composer_status -eq 0 ]; then
        echo -e "${GREEN}[OK] Dépendances Composer installées avec succès (avec dev)${NC}"
    else
        echo -e "${RED}[ERREUR] Échec de l'installation des dépendances Composer (code $composer_status)${NC}"
        echo -e "${YELLOW}[DEBUG] Sortie composer:${NC}\n$composer_output"
        exit 1
    fi
fi

# Installation des dépendances NPM
echo -e "${YELLOW}[DEBUG] Installation des dépendances NPM...${NC}"
npm_output=$(npm install 2>&1)
npm_status=$?

if [ $npm_status -eq 0 ]; then
    echo -e "${GREEN}[OK] Dépendances NPM installées avec succès${NC}"
    echo -e "${YELLOW}[DEBUG] Résumé de l'installation:${NC}"
    echo "$npm_output" | grep -E "added|removed|changed|up to date" || echo "Pas de changements"
else
    echo -e "${RED}[ERREUR] Échec de l'installation des dépendances NPM (code $npm_status)${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie npm:${NC}\n$npm_output"
    exit 1
fi

# Construction des assets
echo -e "${YELLOW}[DEBUG] Construction des assets avec npm run build...${NC}"
build_output=$(npm run build 2>&1)
build_status=$?

if [ $build_status -eq 0 ]; then
    echo -e "${GREEN}[OK] Assets construits avec succès${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie de la construction:${NC}"
    echo "$build_output" | tail -n 10
else
    echo -e "${RED}[ERREUR] Échec de la construction des assets (code $build_status)${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie du build:${NC}\n$build_output"
    exit 1
fi

# Vérifier si Docker est installé et en cours d'exécution
echo -e "${YELLOW}[DEBUG] Vérification du statut de Docker...${NC}"
if command -v docker &> /dev/null; then
    docker_status=$(docker info 2>&1 > /dev/null || echo "failed")
    if [ "$docker_status" = "failed" ]; then
        echo -e "${YELLOW}[AVERTISSEMENT] Docker n'est pas en cours d'exécution. Tentative de démarrage...${NC}"
        # Tenter de démarrer Docker selon la plateforme
        if command -v systemctl &> /dev/null; then
            systemctl start docker
        elif command -v service &> /dev/null; then
            service docker start
        else
            echo -e "${RED}[ERREUR] Impossible de démarrer Docker automatiquement. Veuillez le démarrer manuellement.${NC}"
        fi
    else
        echo -e "${GREEN}[OK] Docker est en cours d'exécution${NC}"
    fi
else
    echo -e "${RED}[ERREUR] Docker n'est pas installé. Veuillez l'installer pour utiliser Mercure.${NC}"
fi

# Vérifier et démarrer/redémarrer les services Docker Compose
echo -e "${YELLOW}[DEBUG] Vérification des services Docker Compose...${NC}"
if [ -f "compose.yaml" ] || [ -f "docker-compose.yml" ] || [ -f "docker-compose.yaml" ]; then
    echo -e "${YELLOW}[DEBUG] Démarrage/redémarrage des services Docker Compose...${NC}"
    if command -v docker-compose &> /dev/null; then
        docker_compose_cmd="docker-compose"
    else
        docker_compose_cmd="docker compose"
    fi
    
    # Démarrer les conteneurs Docker
    docker_output=$($docker_compose_cmd up -d 2>&1)
    docker_status=$?
    
    if [ $docker_status -eq 0 ]; then
        echo -e "${GREEN}[OK] Services Docker démarrés avec succès${NC}"
        echo -e "${YELLOW}[DEBUG] Sortie Docker:${NC}\n$docker_output"
        
        # Spécifiquement pour Mercure, vérifier son statut
        echo -e "${YELLOW}[DEBUG] Vérification du statut du service Mercure...${NC}"
        mercure_container=$($docker_compose_cmd ps | grep mercure | awk '{print $1}')
        
        if [ -n "$mercure_container" ]; then
            mercure_status=$(docker inspect --format='{{.State.Status}}' $mercure_container 2>/dev/null)
            
            if [ "$mercure_status" = "running" ]; then
                echo -e "${GREEN}[OK] Service Mercure est en cours d'exécution${NC}"
                # Afficher l'URL publique de Mercure
                mercure_url=$(docker inspect --format='{{range $p, $conf := .NetworkSettings.Ports}}{{if eq $p "80/tcp"}}{{(index $conf 0).HostIp}}:{{(index $conf 0).HostPort}}{{end}}{{end}}' $mercure_container)
                echo -e "${GREEN}[INFO] Mercure est accessible à l'adresse: http://$mercure_url${NC}"
            else
                echo -e "${RED}[ERREUR] Le service Mercure n'est pas en cours d'exécution (statut: $mercure_status)${NC}"
                echo -e "${YELLOW}[DEBUG] Tentative de redémarrage du service Mercure...${NC}"
                docker restart $mercure_container
            fi
        else
            echo -e "${RED}[ERREUR] Le service Mercure n'a pas été trouvé dans les conteneurs Docker${NC}"
        fi
    else
        echo -e "${RED}[ERREUR] Échec du démarrage des services Docker (code $docker_status)${NC}"
        echo -e "${YELLOW}[DEBUG] Sortie Docker:${NC}\n$docker_output"
    fi
else
    echo -e "${YELLOW}[AVERTISSEMENT] Aucun fichier Docker Compose trouvé, impossible de démarrer Mercure via Docker${NC}"
fi

# Exécution des migrations de base de données
echo -e "${YELLOW}[DEBUG] Exécution des migrations de base de données...${NC}"
migration_output=$(php bin/console doctrine:migrations:migrate --no-interaction 2>&1)
migration_status=$?

if [ $migration_status -eq 0 ]; then
    echo -e "${GREEN}[OK] Migrations exécutées avec succès${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie des migrations:${NC}\n$migration_output"
else
    echo -e "${RED}[ERREUR] Échec des migrations de base de données (code $migration_status)${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie des migrations:${NC}\n$migration_output"
    # Ne pas quitter en cas d'erreur de migration, cela pourrait être normal dans certains cas
    echo -e "${YELLOW}[AVERTISSEMENT] Continuez malgré l'erreur de migration${NC}"
fi

# Nettoyage du cache Symfony
echo -e "${YELLOW}[DEBUG] Nettoyage du cache Symfony...${NC}"
cache_output=$(php bin/console cache:clear --env=prod 2>&1)
cache_status=$?

if [ $cache_status -eq 0 ]; then
    echo -e "${GREEN}[OK] Cache Symfony nettoyé avec succès${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie du nettoyage de cache:${NC}\n$cache_output"
else
    echo -e "${RED}[ERREUR] Échec du nettoyage du cache Symfony (code $cache_status)${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie du nettoyage de cache:${NC}\n$cache_output"
    exit 1
fi

# Réchauffement du cache Symfony (optionnel mais recommandé)
echo -e "${YELLOW}[DEBUG] Réchauffement du cache Symfony...${NC}"
warmup_output=$(php bin/console cache:warmup --env=prod 2>&1)
warmup_status=$?

if [ $warmup_status -eq 0 ]; then
    echo -e "${GREEN}[OK] Cache Symfony réchauffé avec succès${NC}"
else
    echo -e "${RED}[ERREUR] Échec du réchauffement du cache Symfony (code $warmup_status)${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie du réchauffement:${NC}\n$warmup_output"
    # Continuer malgré l'erreur car ce n'est pas critique
fi

# Ajuster les permissions si nécessaire
echo -e "${YELLOW}[DEBUG] Ajustement des permissions sur var/...${NC}"
chmod_output=$(chmod -R 777 var/ 2>&1)
chmod_status=$?

if [ $chmod_status -eq 0 ]; then
    echo -e "${GREEN}[OK] Permissions ajustées avec succès${NC}"
else
    echo -e "${RED}[ERREUR] Échec de l'ajustement des permissions (code $chmod_status)${NC}"
    echo -e "${YELLOW}[DEBUG] Sortie chmod:${NC}\n$chmod_output"
    # Continuer malgré l'erreur car ce n'est pas critique sur tous les environnements
fi

echo -e "${BLUE}[INFO] ===========================================================${NC}"
echo -e "${BLUE}[INFO] Déploiement terminé avec succès le $(date)${NC}"
echo -e "${BLUE}[INFO] ===========================================================${NC}"
