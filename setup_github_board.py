#!/usr/bin/env python3
"""
Crea el tablero Kanban (GitHub Projects v2) para Sistema APA UCM
y vincula los 26 issues.
"""

import os
import requests
import sys
import time

TOKEN = os.environ.get("GITHUB_TOKEN", "")
OWNER = "Tban1"
REPO  = "apa-ucm"

if not TOKEN:
    print("❌ ERROR: exporta GITHUB_TOKEN antes de ejecutar.")
    print("   export GITHUB_TOKEN=ghp_...")
    sys.exit(1)

HEADERS = {"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"}
GQL     = "https://api.github.com/graphql"

def gql(query, variables=None):
    r = requests.post(GQL, headers=HEADERS, json={"query": query, "variables": variables or {}})
    data = r.json()
    if "errors" in data:
        print(f"  ⚠️  {data['errors'][0]['message']}")
    return data

# ── 1. Obtener node ID del owner ──────────────────────────────────────────────
print("\n🔍 Obteniendo ID del usuario...")
res = gql('{ user(login: "%s") { id } }' % OWNER)
owner_id = res["data"]["user"]["id"]
print(f"  ✓  {owner_id}")

# ── 2. Buscar proyecto existente o crear nuevo ────────────────────────────────
print("\n📋 Buscando/creando tablero Kanban...")
res = gql("""
query($login: String!) {
  user(login: $login) {
    projectsV2(first: 20) {
      nodes { id number title url }
    }
  }
}
""", {"login": OWNER})

project = None
for p in res["data"]["user"]["projectsV2"]["nodes"]:
    if "APA UCM" in p["title"]:
        project = p
        print(f"  ↩  Proyecto existente: #{p['number']} — {p['title']}")
        break

if not project:
    res = gql("""
    mutation($ownerId: ID!, $title: String!) {
      createProjectV2(input: { ownerId: $ownerId, title: $title }) {
        projectV2 { id number url title }
      }
    }
    """, {"ownerId": owner_id, "title": "Sistema APA UCM — Kanban"})
    project = res["data"]["createProjectV2"]["projectV2"]
    print(f"  ✓  Proyecto #{project['number']} creado")

project_id  = project["id"]
project_url = project["url"]
print(f"  🔗 {project_url}")

# ── 3. Obtener campo Status ───────────────────────────────────────────────────
print("\n⚙️  Configurando columnas...")
res = gql("""
query($projectId: ID!) {
  node(id: $projectId) {
    ... on ProjectV2 {
      fields(first: 20) {
        nodes {
          ... on ProjectV2SingleSelectField { id name options { id name } }
        }
      }
    }
  }
}
""", {"projectId": project_id})

status_field = next(
    n for n in res["data"]["node"]["fields"]["nodes"]
    if n.get("name") == "Status"
)
status_field_id = status_field["id"]
print(f"  ✓  Campo Status: {[o['name'] for o in status_field['options']]}")

# ── 4. Actualizar columnas ────────────────────────────────────────────────────
COLUMNS = ["Por hacer", "En progreso (WIP: 1)", "En revisión", "Hecho"]

res = gql("""
mutation($fieldId: ID!, $options: [ProjectV2SingleSelectFieldOptionInput!]!) {
  updateProjectV2Field(input: {
    fieldId: $fieldId,
    singleSelectOptions: $options
  }) {
    projectV2Field {
      ... on ProjectV2SingleSelectField { options { id name } }
    }
  }
}
""", {
    "fieldId": status_field_id,
    "options": [{"name": col, "color": "GRAY", "description": ""} for col in COLUMNS]
})

new_options = {
    o["name"]: o["id"]
    for o in res["data"]["updateProjectV2Field"]["projectV2Field"]["options"]
}
print(f"  ✓  Columnas: {list(new_options.keys())}")

# ── 5. Obtener node IDs de los issues ────────────────────────────────────────
print("\n🔗 Obteniendo issues...")
res = gql("""
query($owner: String!, $repo: String!) {
  repository(owner: $owner, name: $repo) {
    issues(first: 100, states: OPEN, orderBy: {field: CREATED_AT, direction: ASC}) {
      nodes { id number title }
    }
  }
}
""", {"owner": OWNER, "repo": REPO})

issue_nodes = res["data"]["repository"]["issues"]["nodes"]
print(f"  ✓  {len(issue_nodes)} issues encontrados")

# ── 6. Agregar issues al proyecto en "Por hacer" ─────────────────────────────
print(f"\n📌 Vinculando issues al tablero...")
todo_id = new_options["Por hacer"]

for i, issue in enumerate(issue_nodes, 1):
    add_res = gql("""
    mutation($projectId: ID!, $contentId: ID!) {
      addProjectV2ItemById(input: { projectId: $projectId, contentId: $contentId }) {
        item { id }
      }
    }
    """, {"projectId": project_id, "contentId": issue["id"]})

    item = add_res.get("data", {}).get("addProjectV2ItemById", {}).get("item")
    if not item:
        print(f"  ↩ [{i:02d}] #{issue['number']} ya estaba en el tablero")
        continue

    item_id = item["id"]
    gql("""
    mutation($projectId: ID!, $itemId: ID!, $fieldId: ID!, $optionId: String!) {
      updateProjectV2ItemFieldValue(input: {
        projectId: $projectId,
        itemId: $itemId,
        fieldId: $fieldId,
        value: { singleSelectOptionId: $optionId }
      }) {
        projectV2Item { id }
      }
    }
    """, {
        "projectId": project_id,
        "itemId": item_id,
        "fieldId": status_field_id,
        "optionId": todo_id
    })

    print(f"  ✓ [{i:02d}/{len(issue_nodes)}] #{issue['number']} {issue['title'][:55]}")
    time.sleep(0.25)

print("\n" + "=" * 60)
print("  ✅ Tablero Kanban listo.")
print(f"  🔗 {project_url}")
print("=" * 60)
