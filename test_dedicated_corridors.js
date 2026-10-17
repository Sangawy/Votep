const fs = require('fs');
const rawTree = JSON.parse(fs.readFileSync('scratch_tree.json', 'utf8'));

// Test Dedicated Family Corridors (Zero-Cross & Zero-Overlap):
// Every brother's family has an exclusive X interval with visible gap.
// Inside each clan, children are placed directly above their father.

function testDedicatedCorridors() {
  const rootX = 0, rootY = 1250;
  const sons = rawTree.children || [];
  const numSons = sons.length;
  const allNodes = [];

  const rx = 74, ry = 24;
  const slotW = rx * 2 + 16; // 164px
  const leafGap = 16;
  const clanGap = 45; // 45px clear airspace between different brother families

  // Step 1: Calculate subtree width within each clan
  function calcClanSubtree(node) {
    if (!node.children || node.children.length === 0) {
      node.subW = slotW;
      return slotW;
    }
    let sum = 0;
    node.children.forEach(ch => {
      sum += calcClanSubtree(ch);
    });
    if (node.children.length > 1) {
      sum += (node.children.length - 1) * leafGap;
    }
    node.subW = Math.max(slotW, sum);
    return node.subW;
  }

  sons.forEach(s => calcClanSubtree(s));

  // Compute total width:
  let totalW = 0;
  sons.forEach(s => totalW += s.subW);
  totalW += (numSons - 1) * clanGap;
  console.log(`Total Tree Width: ${totalW}px`);

  // Step 2: Allocate non-overlapping corridors:
  let curX = -totalW / 2.0;

  rawTree.x = rootX;
  rawTree.y = rootY;
  rawTree.depth = 0;
  allNodes.push(rawTree);

  // Sons placement:
  sons.forEach((son, i) => {
    son.depth = 1;
    son.parent = rawTree;

    const clanMinX = curX;
    const clanMaxX = curX + son.subW;
    const clanCenter = (clanMinX + clanMaxX) / 2.0;
    curX += son.subW + clanGap;

    son.clanMinX = clanMinX;
    son.clanMaxX = clanMaxX;

    // Place the son at the base of his clan corridor:
    // To keep the sons in the graceful arc the user liked ("کوڕەکانی باوە گەورە باش دانراوە"):
    // We place the son at the center of his clan corridor,
    // pulled slightly inward towards the trunk (e.g. 25% pull inward):
    const inwardPull = 0.25;
    son.x = clanCenter * (1 - inwardPull);

    // Arch rise: outer sons curve lower, center sons higher
    const norm = (numSons <= 1) ? 0 : ((i / (numSons - 1)) * 2 - 1);
    const archRise = (1 - Math.abs(norm) * 0.40) * 110;
    const alt = (i % 2 === 0 ? -16 : 16);
    son.y = rootY - (280 + archRise + alt);

    allNodes.push(son);

    // Step 3: Place all descendants STRICTLY inside [clanMinX, clanMaxX]
    function placeDescendantsInClan(parent, pMinX, pMaxX, pY) {
      if (!parent.children || parent.children.length === 0) return;
      const nCh = parent.children.length;

      let totalChW = 0;
      parent.children.forEach(c => totalChW += c.subW);
      totalChW += (nCh - 1) * leafGap;

      // Center the children inside parent's allocated space
      let startX = (pMinX + pMaxX) / 2.0 - totalChW / 2.0;

      parent.children.forEach((ch, ci) => {
        const chMin = startX;
        const chMax = startX + ch.subW;
        startX += ch.subW + leafGap;

        ch.depth = parent.depth + 1;
        ch.parent = parent;
        ch.x = (chMin + chMax) / 2.0;

        // Vertical rise:
        const vAlt = (ci % 2 === 0 ? -22 : 22);
        ch.y = pY - 210 + vAlt;

        allNodes.push(ch);
        placeDescendantsInClan(ch, chMin, chMax, ch.y);
      });
    }

    placeDescendantsInClan(son, clanMinX, clanMaxX, son.y);
  });

  // Verify: Check Clan Boundaries (Zero Cross-over!)
  console.log('\n--- CLAN BOUNDARIES CHECK ---');
  let crossed = false;
  for (let i = 0; i < sons.length - 1; i++) {
    const s1 = sons[i];
    const s2 = sons[i + 1];

    function getClanNodes(n) {
      let l = [n];
      (n.children || []).forEach(c => l = l.concat(getClanNodes(c)));
      return l;
    }
    const c1 = getClanNodes(s1);
    const c2 = getClanNodes(s2);

    const max1 = Math.max(...c1.map(n => n.x + rx));
    const min2 = Math.min(...c2.map(n => n.x - rx));
    const gap = min2 - max1;

    console.log(`[${s1.name}] rightmost: ${Math.round(max1)} | [${s2.name}] leftmost: ${Math.round(min2)} -> Clean Gap: ${Math.round(gap)}px`);
    if (gap < 0) crossed = true;
  }

  // Verify: Collisions between ANY two nodes in the whole tree
  let colls = 0;
  for (let i = 0; i < allNodes.length; i++) {
    for (let j = i + 1; j < allNodes.length; j++) {
      const a = allNodes[i], b = allNodes[j];
      const dx = Math.abs(a.x - b.x);
      const dy = Math.abs(a.y - b.y);
      if (dx < rx * 2 + 4 && dy < ry * 2 + 4) {
        colls++;
        console.log(`COLLISION: [${a.name}] with [${b.name}] (dx=${Math.round(dx)}, dy=${Math.round(dy)})`);
      }
    }
  }

  let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
  allNodes.forEach(n => {
    minX = Math.min(minX, n.x);
    maxX = Math.max(maxX, n.x);
    minY = Math.min(minY, n.y);
    maxY = Math.max(maxY, n.y);
  });

  console.log(`\nClan Separation: ${crossed ? 'FAILED' : '100% PERFECT DISJOINT (ZERO CROSSING)!'}`);
  console.log(`Total Collisions: ${colls}`);
  console.log(`Tree Dimensions: Width = ${Math.round(maxX - minX)}px, Height = ${Math.round(maxY - minY)}px`);
  
  console.log('\nSons positions (The Arc):');
  sons.forEach((s, i) => {
    console.log(` Son ${i+1}: ${s.name} at (${Math.round(s.x)}, ${Math.round(s.y)}), dx from root: ${Math.round(s.x - rootX)}, branch length: ${Math.round(Math.hypot(s.x, rootY - s.y))}`);
  });
}

testDedicatedCorridors();
