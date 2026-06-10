#!/usr/bin/env python3
"""
Configura el tablero Kanban de GitHub Projects al estado correcto
para tomar los screenshots de inicio/cierre de cada Sprint.

Uso:
    python kanban_sprint_screenshot.py <sprint> <momento>

    sprint  : 1 | 2 | 3 | 4 | 5
    momento : inicio | cierre

Ejemplos:
    python kanban_sprint_screenshot.py 2 inicio
      → Sprint 2 en "Por hacer", Sprints 1 en "Hecho"

    python kanban_sprint_screenshot.py 2 cierre
      → Sprints 1-2 en "Hecho"
"""

import os
import sys
import time
import requests

# ── Configuración ─────────────────────────────────────────────────────────────
TOKEN = os.environ.get("GITHUB_TOKEN", "")
OWNER = "Tban1"
REPO  = "apa-ucm"

if not TOKEN:
    print("❌ ERROR: exporta GITHUB_TOKEN antes de ejecutar.")
    print("   export GITHUB_TOKEN=ghp_...")
    sys.exit(1)

HEADERS = {"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"}
GQL     = "https://api.github.com/graphql"

# Mapeo sprint → rango de HUs
SPRINTS = {
    1: list(range(1,  4)),   # HU-001 a HU-003
    2: list(range(4,  9)),   # HU-004 a HU-008
    3: list(range(9,  14)),  # HU-009 a HU-013
    4: list(range(14, 19)),  # HU-014 a HU-018
    5: list(range(19, 27)),  # HU-019 a HU-026
}

def gql(query, variables=None):
    r = requests.post(GQL, headers=HEADERS,
                      json={"query": query, "variables": variables or {}})
    data = r.json()
    if "errors" in data:
        raise RuntimeError(data["errors"][0]["message"])
    return data

# ── 1. Obtener proyecto ───────────────────────────────────────────────────────
def get_project():
    res = gql("""
    query($login: String!) {
      user(login: $login) {
        projectsV2(first: 20) { nodes { id number title url } }
      }
    }
    """, {"login": OWNER})
    for p in res["data"]["user"]["projectsV2"]["nodes"]:
        if "APA UCM" in p["title"]:
            return p
    raise RuntimeError("Proyecto no encontrado. Ejecuta setup_github_board.py primero.")

# ── 2. Obtener campo Status y opciones ────────────────────────────────────────
def get_status_field(project_id):
    res = gql("""
    query($pid: ID!) {
      node(id: $pid) {
        ... on ProjectV2 {
          fields(first: 20) {
            nodes {
              ... on ProjectV2SingleSelectField { id name options { id name } }
            }
          }
        }
      }
    }
    """, {"pid": project_id})
    for f in res["data"]["node"]["fields"]["nodes"]:
        if f.get("name") == "Status":
            return f["id"], {o["name"]: o["id"] for o in f["options"]}
    raise RuntimeError("Campo Status no encontrado.")

# ── 3. Obtener todos los items del proyecto ───────────────────────────────────
def get_project_items(project_id):
    """Devuelve dict {hu_number: item_id}"""
    items = {}
    cursor = None
    while True:
        after = f', after: "{cursor}"' if cursor else ""
        res = gql(f"""
        query($pid: ID!) {{
          node(id: $pid) {{
            ... on ProjectV2 {{
              items(first: 50{after}) {{
                pageInfo {{ hasNextPage endCursor }}
                nodes {{
                  id
                  content {{
                    ... on Issue {{ number title }}
                  }}
                }}
              }}
            }}
          }}
        }}
        """, {"pid": project_id})
        page = res["data"]["node"]["items"]
        for node in page["nodes"]:
            content = node.get("content") or {}
            num = content.get("number")
            if num is not None:
                items[num] = node["id"]
        if not page["pageInfo"]["hasNextPage"]:
            break
        cursor = page["pageInfo"]["endCursor"]
    return items

# ── 4. Actualizar status de un item ──────────────────────────────────────────
def set_status(project_id, item_id, field_id, option_id):
    gql("""
    mutation($pid: ID!, $iid: ID!, $fid: ID!, $oid: String!) {
      updateProjectV2ItemFieldValue(input: {
        projectId: $pid, itemId: $iid, fieldId: $fid,
        value: { singleSelectOptionId: $oid }
      }) { projectV2Item { id } }
    }
    """, {"pid": project_id, "iid": item_id, "fid": field_id, "oid": option_id})

# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    if len(sys.argv) != 3 or sys.argv[1] not in "12345" or sys.argv[2] not in ("inicio", "cierre"):
        print(__doc__)
        sys.exit(1)

    sprint_target = int(sys.argv[1])
    momento       = sys.argv[2]

    # Calcular qué HUs van a qué columna
    # inicio: sprints anteriores → Hecho, sprint actual → Por hacer
    # cierre: sprints hasta el actual → Hecho
    hecho_sprints   = list(range(1, sprint_target))
    por_hacer_sprints = []

    if momento == "inicio":
        por_hacer_sprints = [sprint_target]
    else:  # cierre
        hecho_sprints = list(range(1, sprint_target + 1))

    print(f"\n📋 Configurando tablero para Sprint {sprint_target} — {momento.upper()}")
    print(f"   Hecho    : Sprints {hecho_sprints}")
    if por_hacer_sprints:
        print(f"   Por hacer: Sprints {por_hacer_sprints}")

    print("\n🔍 Conectando con GitHub Projects...")
    project    = get_project()
    project_id = project["id"]
    print(f"   ✓ Proyecto: {project['title']} ({project['url']})")

    field_id, options = get_status_field(project_id)

    # Buscar columnas por nombre (parcial)
    def find_option(keyword):
        for name, oid in options.items():
            if keyword.lower() in name.lower():
                return oid
        raise RuntimeError(f"No se encontró columna con '{keyword}'. Opciones: {list(options.keys())}")

    hecho_id    = find_option("hecho")
    por_hacer_id = find_option("hacer")
    print(f"   ✓ Columnas: Hecho={hecho_id[:8]}…  Por hacer={por_hacer_id[:8]}…")

    print("\n🔗 Obteniendo items del tablero...")
    items = get_project_items(project_id)
    print(f"   ✓ {len(items)} items encontrados")

    # Aplicar estados
    updates = []
    for sprint_num in hecho_sprints:
        for hu in SPRINTS.get(sprint_num, []):
            if hu in items:
                updates.append((hu, items[hu], hecho_id, "Hecho"))

    for sprint_num in por_hacer_sprints:
        for hu in SPRINTS.get(sprint_num, []):
            if hu in items:
                updates.append((hu, items[hu], por_hacer_id, "Por hacer"))

    print(f"\n⚙️  Actualizando {len(updates)} items...")
    for hu_num, item_id, option_id, col_name in updates:
        set_status(project_id, item_id, field_id, option_id)
        print(f"   ✓ HU-{hu_num:03d} → {col_name}")
        time.sleep(0.2)

    print(f"\n{'='*55}")
    print(f"  ✅ Tablero listo para screenshot Sprint {sprint_target} {momento.upper()}")
    print(f"  🔗 {project['url']}")
    print(f"{'='*55}")
    print(f"\n  📸 Toma el screenshot ahora y guárdalo como:")
    if momento == "inicio":
        print(f"     kanban_sprint{sprint_target}_inicio.png")
    else:
        print(f"     kanban_sprint{sprint_target}_cierre.png")

if __name__ == "__main__":
    main()
