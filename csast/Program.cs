using System;
using System.Collections.Generic;
using NDesk.Options;
using System.IO;
using System.Linq;
using Newtonsoft.Json;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.Text;
using Microsoft.CodeAnalysis.CSharp.Syntax;
using Microsoft.CodeAnalysis.FindSymbols;

namespace csast
{
    public class Program
    {
        public static void Main(string[] args)
        {
            var options = new OptionSet();

            List<string> files;
            try
            {
                files = options.Parse(args);
            }
            catch (OptionException e)
            {
                Console.Write("csast: ");
                Console.WriteLine(e.Message);
                Console.WriteLine("Try `csast --help' for more information.");
                return;
            }

            var results = new Dictionary<string, object>();

            foreach (var file in files)
            {
                var workspace = new AdhocWorkspace();
                var projectId = ProjectId.CreateNewId();
                var versionStamp = VersionStamp.Create();
                var projectInfo = ProjectInfo.Create(
                    projectId,
                    versionStamp,
                    "temp",
                    "temp",
                    LanguageNames.CSharp);
                var project = workspace.AddProject(projectInfo);
                SourceText sourceText;

                using (var reader = new StreamReader(file))
                {
                    sourceText = SourceText.From(
                        reader.ReadToEnd(),
                        System.Text.Encoding.ASCII);
                }

                var document = workspace.AddDocument(project.Id, "File.cs", sourceText);
                project = workspace.CurrentSolution.GetProject(project.Id);

                var semanticModel = document.GetSemanticModelAsync().Result;
                var syntaxTree = document.GetSyntaxTreeAsync().Result;

                results.Add(file, ParseNodeOrToken(syntaxTree.GetRoot(), semanticModel, project.Solution));

                var writer = new StreamWriter(Console.OpenStandardOutput());
                var jsonWriter = new JsonTextWriter(writer);
                var ser = new JsonSerializer();

                ser.Formatting = Formatting.None;

                ser.Serialize(jsonWriter, results);

                jsonWriter.Flush();
            }
        }

        private static Dictionary<string, object> ParseNodeOrToken(SyntaxNodeOrToken o, SemanticModel model, Solution solution)
        {
            var node = o.IsNode ? o.AsNode() : null;
            var token = o.IsToken ? (SyntaxToken?)o.AsToken() : null;

            var dict = new Dictionary<string, object>();
            if (node != null)
            {
                dict["Type"] = "syntax";
                dict["ASTType"] = node.GetType().Name;
                dict["Text"] = node.GetText().ToString();
                dict["TrimmedText"] = node.GetText().ToString().Trim();
                dict["Span"] = GetAdjustedSpan(node.GetLocation().GetLineSpan().Span);
                dict["SpanStart"] = node.SpanStart;
                dict["Children"] = node.ChildNodesAndTokens().Select(x => ParseNodeOrToken(x, model, solution)).Cast<object>().ToList();
                AddAdditionalProperties(dict, node, model, solution);

                var symbol = model.GetSymbolInfo(node).Symbol;
                if (symbol != null)
                {
                    AddSymbolProperties(dict, symbol, model, solution);
                }
                else
                {
                    var declaredSymbol = model.GetDeclaredSymbol(node);
                    if (declaredSymbol != null)
                    {
                        AddSymbolProperties(dict, declaredSymbol, model, solution);
                    }
                }
            }
            if (token != null)
            {
                dict["Type"] = "token";
                dict["ASTType"] = token.Value.GetType().Name;
                dict["Value"] = token.Value.Value;
                dict["Text"] = token.Value.ValueText;
                dict["TrimmedText"] = token.Value.ValueText.Trim();
                dict["Span"] = GetAdjustedSpan(token.Value.GetLocation().GetLineSpan().Span);
                dict["SpanStart"] = token.Value.SpanStart;
                dict["LeadingTrivia"] = ParseTrivia(token.Value.LeadingTrivia);
                dict["TrailingTrivia"] = ParseTrivia(token.Value.TrailingTrivia);
            }
            return dict;
        }

        private static void AddAdditionalProperties(Dictionary<string, object> dict, SyntaxNode node, SemanticModel model, Solution solution)
        {
            var baseTypeDeclarationSyntax = node as BaseTypeDeclarationSyntax;
            if (baseTypeDeclarationSyntax != null)
            {
                AddAdditionalPropertiesBaseTypeDeclarationSyntax(dict, baseTypeDeclarationSyntax, model, solution);
            }

            var classDeclarationSyntax = node as ClassDeclarationSyntax;
            if (classDeclarationSyntax != null)
            {
                AddAdditionalPropertiesClassDeclarationSyntax(dict, classDeclarationSyntax, model, solution);
            }

            var structDeclarationSyntax = node as StructDeclarationSyntax;
            if (structDeclarationSyntax != null)
            {
                AddAdditionalPropertiesStructDeclarationSyntax(dict, structDeclarationSyntax, model, solution);
            }

            var fieldDeclarationSyntax = node as FieldDeclarationSyntax;
            if (fieldDeclarationSyntax != null)
            {
                AddAdditionalPropertiesFieldDeclarationSyntax(dict, fieldDeclarationSyntax, model, solution);
            }

            var propertyDeclarationSyntax = node as PropertyDeclarationSyntax;
            if (propertyDeclarationSyntax != null)
            {
                AddAdditionalPropertiesPropertyDeclarationSyntax(dict, propertyDeclarationSyntax, model, solution);
            }

            var methodDeclarationSyntax = node as MethodDeclarationSyntax;
            if (methodDeclarationSyntax != null)
            {
                AddAdditionalPropertiesMethodDeclarationSyntax(dict, methodDeclarationSyntax, model, solution);
            }

            var variableDeclarationSyntax = node as VariableDeclarationSyntax;
            if (variableDeclarationSyntax != null)
            {
                AddAdditionalPropertiesVariableDeclarationSyntax(dict, variableDeclarationSyntax, model, solution);
            }

            var variableDeclaratorSyntax = node as VariableDeclaratorSyntax;
            if (variableDeclaratorSyntax != null)
            {
                AddAdditionalPropertiesVariableDeclaratorSyntax(dict, variableDeclaratorSyntax, model, solution);
            }
        }

        private static void AddAdditionalPropertiesBaseTypeDeclarationSyntax(Dictionary<string, object> dict, BaseTypeDeclarationSyntax node, SemanticModel model, Solution solution)
        {
            var list = new List<object>();
            if (node.Modifiers != null)
            {
                foreach (var modifier in node.Modifiers)
                {
                    list.Add(modifier.Value);
                }
            }
            dict["Modifiers"] = list;
            if (node.Identifier != null)
            {
                dict["Identifier"] = ParseNodeOrToken(node.Identifier, model, solution);
            }
        }

        private static void AddAdditionalPropertiesClassDeclarationSyntax(Dictionary<string, object> dict, ClassDeclarationSyntax node, SemanticModel model, Solution solution)
        {
            var list = new List<object>();
            if (node.BaseList != null && node.BaseList.Types != null)
            {
                foreach (var subnode in node.BaseList.Types)
                {
                    list.Add(ParseNodeOrToken(subnode, model, solution));
                }
            }
            dict["BaseTypes"] = list;

            list = new List<object>();
            if (node.Members != null)
            {
                foreach (var subnode in node.Members)
                {
                    list.Add(ParseNodeOrToken(subnode, model, solution));
                }
            }
            dict["Members"] = list;
        }

        private static void AddAdditionalPropertiesStructDeclarationSyntax(Dictionary<string, object> dict, StructDeclarationSyntax node, SemanticModel model, Solution solution)
        {
            var list = new List<object>();
            if (node.BaseList != null && node.BaseList.Types != null)
            {
                foreach (var subnode in node.BaseList.Types)
                {
                    list.Add(ParseNodeOrToken(subnode, model, solution));
                }
            }
            dict["BaseTypes"] = list;

            list = new List<object>();
            if (node.Members != null)
            {
                foreach (var subnode in node.Members)
                {
                    list.Add(ParseNodeOrToken(subnode, model, solution));
                }
            }
            dict["Members"] = list;
        }

        private static void AddAdditionalPropertiesFieldDeclarationSyntax(Dictionary<string, object> dict, FieldDeclarationSyntax node, SemanticModel model, Solution solution)
        {
            var list = new List<object>();
            if (node.Modifiers != null)
            {
                foreach (var modifier in node.Modifiers)
                {
                    list.Add(modifier.Value);
                }
            }
            dict["Modifiers"] = list;
        }

        private static void AddAdditionalPropertiesPropertyDeclarationSyntax(Dictionary<string, object> dict, PropertyDeclarationSyntax node, SemanticModel model, Solution solution)
        {
            var list = new List<object>();
            if (node.Modifiers != null)
            {
                foreach (var modifier in node.Modifiers)
                {
                    list.Add(modifier.Value);
                }
            }
            dict["Modifiers"] = list;
            if (node.Type != null)
            {
                dict["PropertyType"] = ParseNodeOrToken(node.Type, model, solution);
            }
            if (node.Identifier != null)
            {
                dict["Identifier"] = ParseNodeOrToken(node.Identifier, model, solution);
            }
        }

        private static void AddAdditionalPropertiesMethodDeclarationSyntax(Dictionary<string, object> dict, MethodDeclarationSyntax node, SemanticModel model, Solution solution)
        {
            var list = new List<object>();
            if (node.Modifiers != null)
            {
                foreach (var modifier in node.Modifiers)
                {
                    list.Add(modifier.Value);
                }
            }
            dict["Modifiers"] = list;
            if (node.ReturnType != null)
            {
                dict["ReturnType"] = ParseNodeOrToken(node.ReturnType, model, solution);
            }
            if (node.Identifier != null)
            {
                dict["Identifier"] = ParseNodeOrToken(node.Identifier, model, solution);
            }
        }

        private static void AddAdditionalPropertiesVariableDeclarationSyntax(Dictionary<string, object> dict, VariableDeclarationSyntax node, SemanticModel model, Solution solution)
        {
            if (node.Type != null)
            {
                dict["DeclarationType"] = ParseNodeOrToken(node.Type, model, solution);
            }
        }

        private static void AddAdditionalPropertiesVariableDeclaratorSyntax(Dictionary<string, object> dict, VariableDeclaratorSyntax node, SemanticModel model, Solution solution)
        {
            dict["Identifier"] = ParseNodeOrToken(node.Identifier, model, solution);
        }

        private static void AddSymbolProperties(Dictionary<string, object> dict, ISymbol symbol, SemanticModel model, Solution solution)
        {
            var references = SymbolFinder.FindReferencesAsync(symbol, solution).Result;
            var list = new List<object>();
            foreach (var reference in references)
            {
                foreach (var location in reference.Locations)
                {
                    list.Add(GetAdjustedSpan(location.Location.GetLineSpan().Span));
                }

                foreach (var location in reference.Definition.Locations)
                {
                    list.Add(GetAdjustedSpan(location.GetLineSpan().Span));
                }
            }
            dict["References"] = list.Distinct().ToList();
        }

        private static List<object> ParseTrivia(IEnumerable<SyntaxTrivia> syntaxTriviaList)
        {
            var list = new List<object>();
            foreach (var trivia in syntaxTriviaList)
            {
                var dict = new Dictionary<string, object>();
                dict["Text"] = trivia.ToFullString();
                dict["Kind"] = trivia.Kind().ToString();
                dict["Span"] = GetAdjustedSpan(trivia.GetLocation().GetLineSpan().Span);
                dict["SpanStart"] = trivia.SpanStart;
                list.Add(dict);
            }
            return list;
        }

        private static LinePositionSpan GetAdjustedSpan(LinePositionSpan span)
        {
            return new LinePositionSpan(
                new LinePosition(span.Start.Line + 1, span.Start.Character + 1),
                new LinePosition(span.End.Line + 1, span.End.Character + 1)
            );
        }
    }
}

